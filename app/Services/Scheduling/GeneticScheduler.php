<?php

namespace App\Services\Scheduling;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{CourseOffering, Curriculum, Room, RoomType, User};

class GeneticScheduler
{
    private CourseOffering $offering;
    private array $plan;

    // GA params
    private int $POP_SIZE;
    private int $GENERATIONS;
    private int $ELITE;
    private float $CROSSOVER_RATE;
    private float $MUTATION_RATE;
    private int $TOURNAMENT_K;

    // policies
    private bool $STRICT_SPECIALIZATION_ONLY = true;
    private bool $ALLOW_IF_SUBJECT_NO_SPEC   = false;
    private bool $FALLBACK_IF_NO_MATCH       = false;
    private bool $ENFORCE_PT_AVAIL_STRICT    = true; // applies to ALL faculty

    // time grid
    private array $dayPairs = [['MON','WED'], ['TUE','THU']];

    /**
     * Slot choices for PAIR (90 mins per day).
     * SINGLE uses merged 3-hour blocks built from these.
     */
    private array $slots = [
        ['07:30','09:00'],
        ['09:00','10:30'],
        ['10:30','12:00'],
        ['13:00','14:30'],
        ['14:30','16:00'],
        ['16:00','17:30'],
        ['17:30','19:00'],
        ['19:00','20:30'],
    ];

    // pools
    private Collection $faculty;
    private Collection $rooms;

    // caches
    private array $maxUnits = [];             // [fid => max units]
    private array $currentLoad = [];          // [fid => current term units]
    private array $facultySpecs = [];         // [fid => [spec_id...]]
    private array $facultyAvail = [];         // [fid => [DAY => [[s,e]...]]]
    private array $existingRoom = [];         // [rid][DAY] => [[s,e]...]
    private array $existingFaculty = [];      // [fid][DAY] => [[s,e]...]

    private array $currById = [];             // [cid => Curriculum]
    private array $unitsByCurr = [];          // [cid => units]
    private array $typeByCurr = [];           // [cid => 'LAB'|'LEC']
    private array $eligibleFacultyByCurr = []; // [cid => [fid...]]
    private array $candidateRoomsByCurr = [];  // [cid => [rid...]]

    public function __construct(CourseOffering $offering, array $plan, array $params = [])
    {
        $this->offering = $offering;
        $this->plan     = $plan;

        $this->POP_SIZE       = (int)($params['pop'] ?? 70);
        $this->GENERATIONS    = (int)($params['gen'] ?? 160);
        $this->ELITE          = (int)($params['elite'] ?? 12);
        $this->CROSSOVER_RATE = (float)($params['cx'] ?? 0.75);
        $this->MUTATION_RATE  = (float)($params['mut'] ?? 0.20);
        $this->TOURNAMENT_K   = (int)($params['tk'] ?? 3);

        if (isset($params['STRICT_SPECIALIZATION_ONLY'])) $this->STRICT_SPECIALIZATION_ONLY = (bool)$params['STRICT_SPECIALIZATION_ONLY'];
        if (isset($params['ALLOW_IF_SUBJECT_NO_SPEC']))   $this->ALLOW_IF_SUBJECT_NO_SPEC   = (bool)$params['ALLOW_IF_SUBJECT_NO_SPEC'];
        if (isset($params['FALLBACK_IF_NO_MATCH']))       $this->FALLBACK_IF_NO_MATCH       = (bool)$params['FALLBACK_IF_NO_MATCH'];
        if (isset($params['ENFORCE_PT_AVAIL_STRICT']))    $this->ENFORCE_PT_AVAIL_STRICT    = (bool)$params['ENFORCE_PT_AVAIL_STRICT'];
    }

    public function run(): array
    {
        $this->bootstrap();

        $allIds = $this->curriculumIds();
        if (empty($allIds)) {
            return [
                'meetings'=>[],
                'loads'=>[],
                'score'=>-INF,
                'inc'=>[],
                'debug'=>$this->buildDebug($allIds),
            ];
        }

        // only schedule schedulable subjects (partial scheduling)
        $schedulableIds = array_values(array_filter($allIds, function($cid){
            $cid = (int)$cid;
            $hasFaculty = !empty($this->eligibleFacultyByCurr[$cid] ?? []);
            $hasRooms   = !empty($this->candidateRoomsByCurr[$cid] ?? []);
            $units      = (int)($this->unitsByCurr[$cid] ?? 0);
            return $hasFaculty && $hasRooms && $units > 0;
        }));

        if (empty($schedulableIds)) {
            $inc = [];
            foreach ($allIds as $cid) $inc[(int)$cid] = $this->diagnoseImpossible((int)$cid);

            return [
                'meetings'=>[],
                'loads'=>[],
                'score'=>-INF,
                'inc'=>$inc,
                'debug'=>$this->buildDebug($allIds),
            ];
        }

        // init population
        $pop = [];
        for ($i=0; $i<$this->POP_SIZE; $i++) {
            $c = $this->randomChromosome($schedulableIds);
            $pop[] = ['c'=>$c, 'f'=>$this->fitness($c)];
        }

        // evolve
        for ($g=0; $g<$this->GENERATIONS; $g++) {
            usort($pop, fn($a,$b) => $b['f'] <=> $a['f']);
            $next = array_slice($pop, 0, $this->ELITE);

            while (count($next) < $this->POP_SIZE) {
                $p1 = $this->tournamentPick($pop);
                $p2 = $this->tournamentPick($pop);

                if ((mt_rand()/mt_getrandmax()) < $this->CROSSOVER_RATE) {
                    $child = $this->crossover($p1['c'], $p2['c'], $schedulableIds);
                } else {
                    $child = $this->cloneChromosome($p1['c']);
                }

                $this->mutate($child, $schedulableIds);
                $next[] = ['c'=>$child, 'f'=>$this->fitness($child)];
            }

            $pop = $next;
        }

        usort($pop, fn($a,$b) => $b['f'] <=> $a['f']);

        // ✅ decode + post-check gate
        return $this->decodeResult($pop[0]['c'], $pop[0]['f'], $allIds);
    }

    // ───────────────────────── bootstrap ─────────────────────────

    private function bootstrap(): void
    {
        $this->faculty = $this->teachingFaculty();

        // ✅ ACTIVE ROOMS ONLY
        $this->rooms = $this->activeRoomsQuery()->orderBy('code')->get(['id','code','room_type_id']);

        $facultyIds = $this->faculty->pluck('id')->map(fn($x) => (int)$x)->all();

        $this->maxUnits     = $this->computeMaxUnits($facultyIds);
        $this->currentLoad  = $this->currentTermLoads();
        $this->facultySpecs = $this->facultySpecMap($facultyIds);

        $this->facultyAvail = $this->loadFacultyAvailability($facultyIds);

        $this->preloadExistingConflicts(); // ✅ current term only if academic_id exists
        $this->loadCurriculaAndPools();
    }

    private function activeRoomsQuery()
    {
        $q = Room::query();

        // detect common "active" columns
        if (Schema::hasColumn('rooms', 'is_active')) {
            $q->where('is_active', 1);
        } elseif (Schema::hasColumn('rooms', 'active')) {
            $q->where('active', 1);
        } elseif (Schema::hasColumn('rooms', 'status')) {
            // status-based: 'active' vs 'inactive'
            $q->whereRaw('LOWER(status) = ?', ['active']);
        }

        return $q;
    }

    private function curriculumIds(): array
    {
        $ids = [];
        foreach ($this->plan as $r) {
            if (!empty($r['curriculum_id'])) $ids[] = (int)$r['curriculum_id'];
        }
        return array_values(array_unique($ids));
    }

    private function loadCurriculaAndPools(): void
    {
        $ids = $this->curriculumIds();
        $this->currById = [];

        if (!empty($ids)) {
            $this->currById = Curriculum::whereIn('id', $ids)->get()->keyBy('id')->all();
        }

        foreach ($this->currById as $cid => $c) {
            $cid = (int)$cid;

            $this->unitsByCurr[$cid] = $this->subjectUnits($c);
            $this->typeByCurr[$cid]  = $this->majorityType($c);

            $this->eligibleFacultyByCurr[$cid] = $this->eligibleFacultyForSubject($c);
            $this->candidateRoomsByCurr[$cid]  = $this->candidateRoomsForSubject($this->typeByCurr[$cid]);
        }
    }

    // ───────────────────────── faculty pool + specialization ─────────────────────────

    private function teachingFaculty(): Collection
    {
        $q = User::query()->orderBy('name');

        if (Schema::hasTable('user_specializations')) {
            $q->whereIn('id', function ($sub) {
                $sub->select('user_id')->from('user_specializations');
            });
        } elseif (Schema::hasColumn('users','role')) {
            $q->whereIn('role', ['Faculty','faculty','FACULTY','Teacher','teacher']);
        }

        return $q->get(['id','name','max_units']);
    }

    private function facultySpecMap(array $facultyIds): array
    {
        if (!Schema::hasTable('user_specializations')) return [];

        $rows = DB::table('user_specializations')
            ->whereIn('user_id', $facultyIds)
            ->get(['user_id','specialization_id']);

        $map = [];
        foreach ($rows as $r) {
            $uid = (int)$r->user_id;
            $sid = (int)$r->specialization_id;
            if ($sid <= 0) continue;

            $map[$uid] ??= [];
            $map[$uid][] = $sid;
        }

        foreach ($facultyIds as $fid) {
            $fid = (int)$fid;
            $map[$fid] = array_values(array_unique($map[$fid] ?? []));
        }

        return $map;
    }

    private function eligibleFacultyForSubject(Curriculum $c): array
    {
        $specId = (int)($c->specialization_id ?? 0);
        $eligible = [];

        foreach ($this->faculty as $f) {
            $fid = (int)$f->id;

            $max = (int)($this->maxUnits[$fid] ?? 0);
            if ($max <= 0) continue;

            $cur = (int)($this->currentLoad[$fid] ?? 0);
            if ($cur >= $max) continue;

            if ($this->ENFORCE_PT_AVAIL_STRICT && !$this->hasAnyAvailability($fid)) {
                continue;
            }

            if ($this->STRICT_SPECIALIZATION_ONLY) {
                if ($specId <= 0 && !$this->ALLOW_IF_SUBJECT_NO_SPEC) continue;

                if ($specId > 0) {
                    $specs = $this->facultySpecs[$fid] ?? [];
                    if (!in_array($specId, $specs, true)) continue;
                }
            }

            $eligible[] = $fid;
        }

        usort($eligible, fn($a,$b) =>
            (int)($this->currentLoad[$a] ?? 0) <=> (int)($this->currentLoad[$b] ?? 0)
        );

        return $eligible;
    }

    // ───────────────────────── rooms ─────────────────────────

    private function candidateRoomsForSubject(string $type): array
    {
        // ✅ based on ACTIVE rooms pool ($this->rooms already filtered)
        if (Schema::hasTable('room_types') && Schema::hasColumn('rooms','room_type_id')) {
            $typeId = $this->roomTypeIdByMajority($type);

            if ($typeId) {
                $ids = $this->rooms->where('room_type_id', (int)$typeId)->pluck('id')->all();
                if (!empty($ids)) return array_map('intval', $ids);
            }

            if ($type === 'LAB') return [];
        }

        return $this->rooms->pluck('id')->map(fn($x) => (int)$x)->all();
    }

    // ───────────────────────── INTERNAL CONFLICT HELPERS ─────────────────────────

    private function addBusy(array &$map, int $id, string $day, string $s, string $e): void
    {
        $day = $this->normalizeDay($day) ?? strtoupper($day);
        $map[$id] ??= [];
        $map[$id][$day] ??= [];
        $map[$id][$day][] = [$s,$e];
    }

    private function hasBusy(array $map, int $id, string $day, string $s, string $e): bool
    {
        $day = $this->normalizeDay($day) ?? strtoupper($day);
        foreach (($map[$id][$day] ?? []) as $w) {
            if ($this->overlaps($w[0], $w[1], $s, $e)) return true;
        }
        return false;
    }

    private function hasBusyPair(array $map, int $id, array $days, string $s, string $e): bool
    {
        return $this->hasBusy($map, $id, $days[0], $s, $e)
            || $this->hasBusy($map, $id, $days[1], $s, $e);
    }

    // ───────────────────────── GA ops ─────────────────────────

    private function randomChromosome(array $currIds): array
    {
        $chrom = [];
        $tmpLoad = $this->currentLoad;

        // ✅ internal conflicts within same chromosome
        $internalRoom = [];
        $internalFaculty = [];

        foreach ($currIds as $cid) {
            $c = $this->currById[$cid] ?? null;
            if (!$c) { $chrom[$cid] = null; continue; }

            $units = (int)($this->unitsByCurr[$cid] ?? 0);
            $type  = (string)($this->typeByCurr[$cid] ?? 'LEC');

            $facultyIds = $this->eligibleFacultyByCurr[$cid] ?? [];
            $roomIds    = $this->candidateRoomsByCurr[$cid] ?? [];

            if (empty($facultyIds) || empty($roomIds) || $units <= 0) {
                $chrom[$cid] = null;
                continue;
            }

            $gene = null;

            for ($k=0; $k<240; $k++) {
                $fid = (int)$facultyIds[array_rand($facultyIds)];
                $rid = (int)$roomIds[array_rand($roomIds)];

                $max = (int)($this->maxUnits[$fid] ?? 0);
                if ($max <= 0) continue;
                if ((($tmpLoad[$fid] ?? 0) + $units) > $max) continue;

                if ($type !== 'LAB') {
                    $pair = $this->dayPairs[array_rand($this->dayPairs)];
                    $slot = $this->slots[array_rand($this->slots)];
                    $st = $slot[0]; $et = $slot[1];

                    if (!$this->coversPair($fid, $pair, $st, $et)) continue;

                    // DB conflicts (current term only)
                    if ($this->conflictRoomPair($rid, $pair, $st, $et)) continue;
                    if ($this->conflictFacultyPair($fid, $pair, $st, $et)) continue;

                    // internal conflicts
                    if ($this->hasBusyPair($internalRoom, $rid, $pair, $st, $et)) continue;
                    if ($this->hasBusyPair($internalFaculty, $fid, $pair, $st, $et)) continue;

                    $gene = [
                        'kind' => 'PAIR',
                        'days' => $pair,
                        'start'=> $st,
                        'end'  => $et,
                        'room_id'=>$rid,
                        'faculty_id'=>$fid,
                        'type'=>$type,
                    ];

                    $this->addBusy($internalRoom, $rid, $pair[0], $st, $et);
                    $this->addBusy($internalRoom, $rid, $pair[1], $st, $et);
                    $this->addBusy($internalFaculty, $fid, $pair[0], $st, $et);
                    $this->addBusy($internalFaculty, $fid, $pair[1], $st, $et);

                } else {
                    $wins = $this->merged3hSlots();
                    if (empty($wins)) continue;

                    $w = $wins[array_rand($wins)];
                    $st = $w[0]; $et = $w[1];
                    $day = $this->randomLabDay();

                    if (!$this->coversWindow($fid, $day, $st, $et)) continue;

                    // DB conflicts
                    if ($this->conflictRoomSingle($rid, $day, $st, $et)) continue;
                    if ($this->conflictFacultySingle($fid, $day, $st, $et)) continue;

                    // internal conflicts
                    if ($this->hasBusy($internalRoom, $rid, $day, $st, $et)) continue;
                    if ($this->hasBusy($internalFaculty, $fid, $day, $st, $et)) continue;

                    $gene = [
                        'kind'=>'SINGLE',
                        'day'=>$day,
                        'start'=>$st,
                        'end'=>$et,
                        'room_id'=>$rid,
                        'faculty_id'=>$fid,
                        'type'=>$type,
                    ];

                    $this->addBusy($internalRoom, $rid, $day, $st, $et);
                    $this->addBusy($internalFaculty, $fid, $day, $st, $et);
                }

                if ($gene) {
                    $tmpLoad[$fid] = ($tmpLoad[$fid] ?? 0) + $units;
                    break;
                }
            }

            $chrom[$cid] = $gene;
        }

        return $chrom;
    }

    private function fitness(array $chrom): float
    {
        $score = 0.0;

        $P_UNASSIGNED = 600;
        $P_CONFLICT   = 250;
        $P_AVAIL      = 250;
        $P_CAP        = 1500;

        $tmpLoad = $this->currentLoad;

        // internal conflicts within chromosome
        $internalRoom = [];
        $internalFaculty = [];

        foreach ($chrom as $cid => $gene) {
            if (!$gene) { $score -= $P_UNASSIGNED; continue; }

            $fid = (int)($gene['faculty_id'] ?? 0);
            $rid = (int)($gene['room_id'] ?? 0);
            $units = (int)($this->unitsByCurr[$cid] ?? 0);

            if (!$fid || !$rid) { $score -= $P_UNASSIGNED; continue; }

            $max = (int)($this->maxUnits[$fid] ?? 0);
            if ($max <= 0) { $score -= $P_CAP; continue; }
            if ((($tmpLoad[$fid] ?? 0) + $units) > $max) { $score -= $P_CAP; continue; }

            $tmpLoad[$fid] = ($tmpLoad[$fid] ?? 0) + $units;

            if (($gene['kind'] ?? '') === 'PAIR') {
                $days = $gene['days'] ?? [];
                $st = (string)$gene['start'];
                $et = (string)$gene['end'];

                if (!$this->coversPair($fid, $days, $st, $et)) $score -= $P_AVAIL;

                if ($this->conflictRoomPair($rid, $days, $st, $et)) $score -= $P_CONFLICT;
                if ($this->conflictFacultyPair($fid, $days, $st, $et)) $score -= $P_CONFLICT;

                if ($this->hasBusyPair($internalRoom, $rid, $days, $st, $et)) $score -= $P_CONFLICT;
                if ($this->hasBusyPair($internalFaculty, $fid, $days, $st, $et)) $score -= $P_CONFLICT;

                $this->addBusy($internalRoom, $rid, $days[0], $st, $et);
                $this->addBusy($internalRoom, $rid, $days[1], $st, $et);
                $this->addBusy($internalFaculty, $fid, $days[0], $st, $et);
                $this->addBusy($internalFaculty, $fid, $days[1], $st, $et);

            } else {
                $d  = (string)($gene['day'] ?? '');
                $st = (string)$gene['start'];
                $et = (string)($gene['end'] ?? '');

                if (!$this->coversWindow($fid, $d, $st, $et)) $score -= $P_AVAIL;

                if ($this->conflictRoomSingle($rid, $d, $st, $et)) $score -= $P_CONFLICT;
                if ($this->conflictFacultySingle($fid, $d, $st, $et)) $score -= $P_CONFLICT;

                if ($this->hasBusy($internalRoom, $rid, $d, $st, $et)) $score -= $P_CONFLICT;
                if ($this->hasBusy($internalFaculty, $fid, $d, $st, $et)) $score -= $P_CONFLICT;

                $this->addBusy($internalRoom, $rid, $d, $st, $et);
                $this->addBusy($internalFaculty, $fid, $d, $st, $et);
            }
        }

        return $score;
    }

    /**
     * ✅ FINAL GATE: post-check before returning meetings.
     * - Ensures no internal overlaps (faculty/room).
     * - If conflict detected, mark that subject INC and skip inserting its meetings.
     */
    private function sanitizeChromosomeBeforeInsert(array $chrom, array &$inc): array
    {
        $safe = [];
        $internalRoom = [];
        $internalFaculty = [];

        foreach ($chrom as $cid => $gene) {
            if (!$gene) { $safe[$cid] = null; continue; }

            $cid = (int)$cid;
            $fid = (int)($gene['faculty_id'] ?? 0);
            $rid = (int)($gene['room_id'] ?? 0);

            if ($fid <= 0 || $rid <= 0) {
                $inc[$cid] = 'Post-check: Missing faculty/room.';
                $safe[$cid] = null;
                continue;
            }

            if (($gene['kind'] ?? '') === 'PAIR') {
                $days = $gene['days'] ?? [];
                $st = (string)$gene['start'];
                $et = (string)$gene['end'];

                // internal overlap?
                if ($this->hasBusyPair($internalFaculty, $fid, $days, $st, $et) ||
                    $this->hasBusyPair($internalRoom, $rid, $days, $st, $et)
                ) {
                    $inc[$cid] = 'Post-check: Internal conflict detected (faculty/room overlap).';
                    $safe[$cid] = null;
                    continue;
                }

                // mark busy
                $this->addBusy($internalRoom, $rid, $days[0], $st, $et);
                $this->addBusy($internalRoom, $rid, $days[1], $st, $et);
                $this->addBusy($internalFaculty, $fid, $days[0], $st, $et);
                $this->addBusy($internalFaculty, $fid, $days[1], $st, $et);

                $safe[$cid] = $gene;
                continue;
            }

            // SINGLE
            $d  = (string)($gene['day'] ?? '');
            $st = (string)$gene['start'];
            $et = (string)($gene['end'] ?? '');

            if ($this->hasBusy($internalFaculty, $fid, $d, $st, $et) ||
                $this->hasBusy($internalRoom, $rid, $d, $st, $et)
            ) {
                $inc[$cid] = 'Post-check: Internal conflict detected (faculty/room overlap).';
                $safe[$cid] = null;
                continue;
            }

            $this->addBusy($internalRoom, $rid, $d, $st, $et);
            $this->addBusy($internalFaculty, $fid, $d, $st, $et);

            $safe[$cid] = $gene;
        }

        return $safe;
    }

    private function decodeResult(array $chrom, float $score, array $allIds): array
    {
        $meetings = [];
        $loads = [];
        $inc = [];

        // pre-inc for unscheduled
        foreach ($allIds as $cid) {
            $cid = (int)$cid;
            if (empty($chrom[$cid] ?? null)) {
                $inc[$cid] = $this->diagnoseImpossible($cid);
            }
        }

        // ✅ final safety gate
        $chrom = $this->sanitizeChromosomeBeforeInsert($chrom, $inc);

        foreach ($allIds as $cid) {
            $cid = (int)$cid;
            $gene = $chrom[$cid] ?? null;

            if (!$gene) continue;

            $fid = (int)($gene['faculty_id'] ?? 0);
            $rid = (int)($gene['room_id'] ?? 0);
            $type = (string)($gene['type'] ?? 'LEC');

            if (!$fid || !$rid) {
                $inc[$cid] = 'Missing faculty or room.';
                continue;
            }

            if (($gene['kind'] ?? '') === 'PAIR') {
                foreach (($gene['days'] ?? []) as $d) {
                    $meetings[] = [
                        'offering_id'   => (int)$this->offering->id,
                        'curriculum_id' => $cid,
                        'faculty_id'    => $fid,
                        'room_id'       => $rid,
                        'day'           => strtoupper((string)$d),
                        'start_time'    => (string)$gene['start'],
                        'end_time'      => (string)$gene['end'],
                        'type'          => $type,
                        'notes'         => null,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ];
                }
            } else {
                $meetings[] = [
                    'offering_id'   => (int)$this->offering->id,
                    'curriculum_id' => $cid,
                    'faculty_id'    => $fid,
                    'room_id'       => $rid,
                    'day'           => strtoupper((string)$gene['day']),
                    'start_time'    => (string)$gene['start'],
                    'end_time'      => (string)$gene['end'],
                    'type'          => $type,
                    'notes'         => null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            $loads[] = [
                'faculty_id'    => $fid,
                'curriculum_id' => $cid,
                'contact_hours' => (int)($this->unitsByCurr[$cid] ?? 0),
            ];
        }

        return [
            'meetings' => $meetings,
            'loads'    => $loads,
            'score'    => $score,
            'inc'      => $inc,
            'debug'    => $this->buildDebug($allIds),
        ];
    }

    private function diagnoseImpossible(int $cid): string
    {
        $c = $this->currById[$cid] ?? null;
        if (!$c) return 'Curriculum not found.';

        $units = (int)($this->unitsByCurr[$cid] ?? 0);
        if ($units <= 0) return 'Units is 0/invalid.';

        $specId = (int)($c->specialization_id ?? 0);
        if ($this->STRICT_SPECIALIZATION_ONLY && $specId <= 0 && !$this->ALLOW_IF_SUBJECT_NO_SPEC) {
            return 'No specialization_id (STRICT).';
        }

        $ef = count($this->eligibleFacultyByCurr[$cid] ?? []);
        if ($ef <= 0) return 'INC: No eligible faculty matches specialization / availability / max units.';

        $cr = count($this->candidateRoomsByCurr[$cid] ?? []);
        if ($cr <= 0) return 'INC: No candidate rooms (room type mismatch OR rooms inactive).';

        return 'INC: No feasible slot due to conflicts/availability.';
    }

    private function buildDebug(array $ids): array
    {
        $debugCurr = [];
        foreach ($ids as $cid) {
            $cid = (int)$cid;
            $c = $this->currById[$cid] ?? null;
            $debugCurr[] = [
                'cid' => $cid,
                'spec_id' => (int)($c?->specialization_id ?? 0),
                'eligible_faculty' => count($this->eligibleFacultyByCurr[$cid] ?? []),
                'candidate_rooms'  => count($this->candidateRoomsByCurr[$cid] ?? []),
                'type'             => $this->typeByCurr[$cid] ?? null,
                'units'            => $this->unitsByCurr[$cid] ?? null,
            ];
        }

        return [
            'faculty_pool' => (int)$this->faculty->count(),
            'rooms_pool'   => (int)$this->rooms->count(),
            'curricula'    => $debugCurr,
        ];
    }

    private function tournamentPick(array $pop): array
    {
        $best = null;
        for ($i=0; $i<$this->TOURNAMENT_K; $i++) {
            $cand = $pop[array_rand($pop)];
            if ($best === null || $cand['f'] > $best['f']) $best = $cand;
        }
        return $best;
    }

    private function cloneChromosome(array $c): array
    {
        return unserialize(serialize($c));
    }

    private function crossover(array $a, array $b, array $currIds): array
    {
        $child = [];
        $cut = (int)floor(count($currIds)/2);

        $shuffled = $currIds;
        shuffle($shuffled);

        $left = array_slice($shuffled, 0, $cut);
        $leftSet = array_fill_keys($left, true);

        foreach ($currIds as $cid) {
            $child[$cid] = isset($leftSet[$cid]) ? ($a[$cid] ?? null) : ($b[$cid] ?? null);
        }

        return $child;
    }

    private function mutate(array &$c, array $currIds): void
    {
        if ((mt_rand()/mt_getrandmax()) > $this->MUTATION_RATE) return;

        $count = rand(1, 3);
        for ($i=0; $i<$count; $i++) {
            $cid = $currIds[array_rand($currIds)];
            $c[$cid] = null;
        }
    }

    // ───────────────────────── availability + conflicts ─────────────────────────

    private function normalizeDay(?string $day): ?string
    {
        if (!$day) return null;
        $d = strtoupper(trim($day));

        $map = [
            'MONDAY'=>'MON','MON'=>'MON',
            'TUESDAY'=>'TUE','TUE'=>'TUE','TUES'=>'TUE',
            'WEDNESDAY'=>'WED','WED'=>'WED',
            'THURSDAY'=>'THU','THU'=>'THU','THURS'=>'THU',
            'FRIDAY'=>'FRI','FRI'=>'FRI',
            'SATURDAY'=>'SAT','SAT'=>'SAT',
            'SUNDAY'=>'SUN','SUN'=>'SUN',
        ];

        if (str_contains($d,'/')) {
            $parts = array_map('trim', explode('/', $d));
            $parts = array_values(array_filter(array_map(fn($p) => $map[$p] ?? null, $parts)));
            return empty($parts) ? null : implode('/', $parts);
        }

        return $map[$d] ?? null;
    }

    private function hasAnyAvailability(int $uid): bool
    {
        $avail = $this->facultyAvail[$uid] ?? [];
        if (empty($avail)) return false;

        foreach ($avail as $wins) {
            if (!empty($wins)) return true;
        }
        return false;
    }

    private function coversWindow(int $uid, string $day, string $start, string $end): bool
    {
        $day = $this->normalizeDay($day) ?? strtoupper($day);
        if (!$this->ENFORCE_PT_AVAIL_STRICT) return true;

        $wins = $this->facultyAvail[$uid][$day] ?? [];
        if (empty($wins)) return false;

        foreach ($wins as $w) {
            $s = $w[0]; $e = $w[1];
            if ($s <= $start && $e >= $end) return true;
        }
        return false;
    }

    private function coversPair(int $uid, array $days, string $start, string $end): bool
    {
        return $this->coversWindow($uid, $days[0], $start, $end)
            && $this->coversWindow($uid, $days[1], $start, $end);
    }

    private function overlaps(string $aS, string $aE, string $bS, string $bE): bool
    {
        return ($aS < $bE) && ($aE > $bS);
    }

    private function conflictRoomSingle(int $rid, string $day, string $s, string $e): bool
    {
        $day = $this->normalizeDay($day) ?? strtoupper($day);
        foreach (($this->existingRoom[$rid][$day] ?? []) as $w) {
            if ($this->overlaps($w[0], $w[1], $s, $e)) return true;
        }
        return false;
    }

    private function conflictFacultySingle(int $fid, string $day, string $s, string $e): bool
    {
        $day = $this->normalizeDay($day) ?? strtoupper($day);
        foreach (($this->existingFaculty[$fid][$day] ?? []) as $w) {
            if ($this->overlaps($w[0], $w[1], $s, $e)) return true;
        }
        return false;
    }

    private function conflictRoomPair(int $rid, array $days, string $s, string $e): bool
    {
        return $this->conflictRoomSingle($rid, $days[0], $s, $e)
            || $this->conflictRoomSingle($rid, $days[1], $s, $e);
    }

    private function conflictFacultyPair(int $fid, array $days, string $s, string $e): bool
    {
        return $this->conflictFacultySingle($fid, $days[0], $s, $e)
            || $this->conflictFacultySingle($fid, $days[1], $s, $e);
    }

    private function merged3hSlots(): array
    {
        $merged = [];
        for ($i=0; $i<count($this->slots)-1; $i++) {
            $merged[] = [$this->slots[$i][0], $this->slots[$i+1][1]];
        }
        return $merged;
    }

    private function randomLabDay(): string
    {
        $days = ['MON','TUE','WED','THU','FRI'];
        return $days[array_rand($days)];
    }

    private function loadFacultyAvailability(array $facultyIds): array
    {
        $out = [];
        if (!Schema::hasTable('faculty_availabilities')) return $out;

        $hasTimeSlotId = Schema::hasColumn('faculty_availabilities', 'time_slot_id')
            && Schema::hasTable('time_slots');

        if ($hasTimeSlotId) {
            $rows = DB::table('faculty_availabilities as fa')
                ->join('time_slots as ts', 'ts.id', '=', 'fa.time_slot_id')
                ->whereIn('fa.user_id', $facultyIds)
                ->where('fa.is_available', 1)
                ->get([
                    'fa.user_id',
                    'fa.day',
                    'ts.start_time',
                    'ts.end_time',
                ]);

            foreach ($rows as $r) {
                $uid = (int)$r->user_id;

                $day = $this->normalizeDay((string)($r->day ?? ''));
                if (!$day) continue;

                $start = substr((string)$r->start_time, 0, 5);
                $end   = substr((string)$r->end_time,   0, 5);

                if (!$start || !$end) continue;

                $out[$uid] ??= [];
                $out[$uid][$day] ??= [];
                $out[$uid][$day][] = [$start, $end];
            }

            return $out;
        }

        // fallback if times stored directly
        $rows = DB::table('faculty_availabilities')
            ->whereIn('user_id', $facultyIds)
            ->where('is_available', 1)
            ->get();

        foreach ($rows as $r) {
            $uid = (int)$r->user_id;

            $day = $this->normalizeDay((string)($r->day ?? ''));
            if (!$day) continue;

            $start = null;
            $end   = null;

            foreach (['start_time','from_time','time_start','start'] as $c) {
                if (isset($r->$c) && $r->$c) { $start = substr((string)$r->$c,0,5); break; }
            }
            foreach (['end_time','to_time','time_end','end'] as $c) {
                if (isset($r->$c) && $r->$c) { $end = substr((string)$r->$c,0,5); break; }
            }

            if (!$start || !$end) continue;

            $out[$uid] ??= [];
            $out[$uid][$day] ??= [];
            $out[$uid][$day][] = [$start, $end];
        }

        return $out;
    }

    private function currentTermLoads(): array
    {
        $loads = [];

        $q = DB::table('section_meetings as sm')
            ->join('course_offerings as co', 'co.id', '=', 'sm.offering_id')
            ->whereNotNull('sm.faculty_id');

        // ✅ current term only
        if (Schema::hasColumn('course_offerings','academic_id') && isset($this->offering->academic_id)) {
            $q->where('co.academic_id', (int)$this->offering->academic_id);
        }

        $rows = $q->get(['sm.faculty_id','sm.curriculum_id']);

        $cids = $rows->pluck('curriculum_id')->unique()->filter()->values()->all();
        $currUnits = [];
        if (!empty($cids)) {
            $currUnits = Curriculum::whereIn('id', $cids)->get()
                ->keyBy('id')
                ->map(fn($c) => (int)($c->units ?? 0))
                ->all();
        }

        foreach ($rows as $r) {
            $fid = (int)$r->faculty_id;
            $cid = (int)$r->curriculum_id;
            $loads[$fid] = ($loads[$fid] ?? 0) + (int)($currUnits[$cid] ?? 0);
        }

        return $loads;
    }

    private function preloadExistingConflicts(): void
    {
        $this->existingRoom = [];
        $this->existingFaculty = [];

        $q = DB::table('section_meetings as sm')
            ->join('course_offerings as co', 'co.id', '=', 'sm.offering_id')
            ->whereNotNull('sm.start_time');

        // ✅ current term only
        if (Schema::hasColumn('course_offerings','academic_id') && isset($this->offering->academic_id)) {
            $q->where('co.academic_id', (int)$this->offering->academic_id);
        }

        $rows = $q->get(['sm.room_id','sm.faculty_id','sm.day','sm.start_time','sm.end_time']);

        foreach ($rows as $m) {
            $day = $this->normalizeDay((string)($m->day ?? '')) ?? strtoupper((string)($m->day ?? ''));
            $s = substr((string)$m->start_time, 0, 5);
            $e = substr((string)$m->end_time,   0, 5);

            if (!empty($m->room_id)) {
                $rid = (int)$m->room_id;
                $this->existingRoom[$rid][$day][] = [$s,$e];
            }

            if (!empty($m->faculty_id)) {
                $fid = (int)$m->faculty_id;
                $this->existingFaculty[$fid][$day][] = [$s,$e];
            }
        }
    }

    private function computeMaxUnits(array $facultyIds): array
    {
        $max = [];

        if (Schema::hasTable('users_employments')) {
            $regCandidates = ['regular_load','regular_units','regular','reg_load','reg_units'];
            $extCandidates = ['extra_load','extra_units','extra','ext_load','ext_units'];

            $regCol = null; $extCol = null;
            foreach ($regCandidates as $c) if (Schema::hasColumn('users_employments', $c)) { $regCol = $c; break; }
            foreach ($extCandidates as $c) if (Schema::hasColumn('users_employments', $c)) { $extCol = $c; break; }

            if ($regCol || $extCol) {
                $cols = ['user_id'];
                if ($regCol) $cols[] = $regCol;
                if ($extCol) $cols[] = $extCol;

                $rows = DB::table('users_employments')->whereIn('user_id', $facultyIds)->get($cols);
                foreach ($rows as $r) {
                    $uid = (int)$r->user_id;
                    $reg = $regCol ? (int)($r->{$regCol} ?? 0) : 0;
                    $ext = $extCol ? (int)($r->{$extCol} ?? 0) : 0;
                    $max[$uid] = $reg + $ext;
                }
            }
        }

        if (Schema::hasColumn('users', 'max_units')) {
            $rows2 = DB::table('users')->whereIn('id', $facultyIds)->pluck('max_units','id')->all();
            foreach ($rows2 as $uid => $v) {
                $uid = (int)$uid;
                if (!isset($max[$uid]) || (int)$max[$uid] <= 0) $max[$uid] = (int)$v;
            }
        }

        foreach ($facultyIds as $id) {
            $id = (int)$id;
            if (!isset($max[$id])) $max[$id] = 0;
        }

        return $max;
    }

    private function majorityType(Curriculum $c): string
    {
        $lec = (int)($c->lec ?? 0);
        $lab = (int)($c->lab ?? 0);
        return ($lab > $lec) ? 'LAB' : 'LEC';
    }

    private function roomTypeIdByMajority(string $major): ?int
    {
        if (!Schema::hasTable('room_types')) return null;

        if ($major === 'LAB') {
            $id = RoomType::whereRaw("UPPER(name) LIKE '%LAB%'")->value('id');
            return $id ? (int)$id : null;
        }

        foreach (['LEC','CLASS','LECTURE','ROOM'] as $k) {
            $id = RoomType::whereRaw("UPPER(name) LIKE ?", ["%{$k}%"])->value('id');
            if ($id) return (int)$id;
        }

        return null;
    }

    private function subjectUnits(Curriculum $c): int
    {
        if (Schema::hasColumn('curricula','units') && $c->units !== null && $c->units !== '') {
            return (int)$c->units;
        }
        return 0;
    }
}
