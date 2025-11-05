<?php

namespace App\Livewire\Head\Schedulings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{
    CourseOffering,
    SectionMeeting,
    Curriculum,
    Room,
    User,
    Specialization,
    RoomType,
    FacultyAvailability,
    TimeSlot
};

#[Title('Editor')]
#[Layout('layouts.head-shell')]
class Editor extends Component
{
    public CourseOffering $offering;

    /** Planner: the ONLY table shown in UI */
    public array $plan = [];
    public bool $planLoaded = false;

    /** Button state: becomes “Generated” only if THIS offering has meetings */
    public bool $alreadyGenerated = false;

    /** Incompleteness flags (for footer actions) */
    public bool $hasUnassigned = false; // true if any faculty_id is missing
    public int  $incompleteCount = 0;

    /** Time grid (1.5h blocks). Now includes evening for PT (until 21:00). */
    private array $dayPairs = [['MON','WED'], ['TUE','THU']];      // 2×1.5h blocks
    private array $slots    = [
        ['08:00','09:30'],
        ['09:30','11:00'],
        // ['11:00','12:30'], // usually overlapped by lunch; blocked by policy below
        ['13:00','14:30'],
        ['14:30','16:00'],
        ['16:00','17:30'],
        // evening extensions
        ['17:30','19:00'],
        ['19:00','20:30'],
    ];

    /** End-time limits per employment type */
    private string $FT_END_LIMIT = '18:00'; // Full-time latest end
    private string $PT_END_LIMIT = '21:00'; // Part-time latest end

    // caches
    private array $facultyLoad = [];            // [user_id => units (teaching + admin)]
    private array $facultySpecializations = []; // [user_id => [specialization_id,...]]
    private array $specializationNames = [];    // [id => name]

    /** AVAILABILITY cache (PT only enforced) */
    private array $facultyAvail = [];           // [user_id => [DAY => [[start,end], ...]]]

    /** POLICY */
    private bool $STRICT_SPECIALIZATION_ONLY = true;   // hard gate: must match specialization/title exactly
    private bool $REQUIRE_EXACT_NAME_MATCH   = true;   // keep strict equality when name-based
    private bool $ENFORCE_PT_AVAIL_STRICT    = true;   // *** now STRICT for part-time ***

    /** Blocked windows (e.g., lunch). Any overlapping slot will be skipped. */
    private array $BLOCKED_WINDOWS = [
        ['12:00','13:00'], // Lunch
    ];

    public function mount(CourseOffering $offering)
    {
        if (!$offering->exists) {
            session()->flash('offerings_warning', 'Pick an offering first.');
            return redirect()->route('head.offerings.index');
        }

        $this->offering = $offering;
        $this->alreadyGenerated = SectionMeeting::where('offering_id',$offering->id)->exists();

        $this->loadPlan();
        $this->hydratePlanFromMeetings();
        $this->recomputeCompleteness();
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // LOAD PLAN
    // ─────────────────────────────────────────────────────────────────────────────
    public function loadPlan(): void
    {
        $yearAliases = $this->yearAliases($this->offering->year_level);
        $semAliases  = $this->semesterAliases(optional($this->offering->academic)->semester);
        $yearCol     = $this->curriculaYearColumn();

        $subjectsQ = Curriculum::query();

        if (Schema::hasColumn('curricula','course_id') && !empty($this->offering->course_id)) {
            $subjectsQ->where('course_id', $this->offering->course_id);
        }

        if ($yearCol && !empty($yearAliases)) {
            $subjectsQ->where(function($qq) use ($yearCol, $yearAliases) {
                foreach ($yearAliases as $alias) {
                    $qq->orWhere($yearCol, 'like', $alias)
                       ->orWhere($yearCol, 'like', "%{$alias}%");
                }
            });
        }

        if (Schema::hasColumn('curricula','semester') && !empty($semAliases)) {
            $subjectsQ->where(function($qq) use ($semAliases) {
                foreach ($semAliases as $alias) {
                    $qq->orWhere('semester','like',$alias)
                       ->orWhere('semester','like',"%{$alias}%");
                }
            });
        }

        $subjectsQ->orderBy('course_code');
        $subjects = $subjectsQ->get();

        // Fallback #1 (loosen)
        if ($subjects->isEmpty()) {
            $looseQ = Curriculum::query();

            if ($yearCol && !empty($yearAliases)) {
                $looseQ->where(function($qq) use ($yearCol, $yearAliases) {
                    foreach ($yearAliases as $alias) {
                        $qq->orWhere($yearCol, 'like', $alias)
                           ->orWhere($yearCol, 'like', "%{$alias}%");
                    }
                });
            }
            if (Schema::hasColumn('curricula','semester') && !empty($semAliases)) {
                $looseQ->where(function($qq) use ($semAliases) {
                    foreach ($semAliases as $alias) {
                        $qq->orWhere('semester','like',$alias)
                           ->orWhere('semester','like',"%{$alias}%");
                    }
                });
            }

            $subjects = $looseQ->orderBy('course_code')->get();
        }

        // Fallback #2 (mirror meetings)
        if ($subjects->isEmpty()) {
            $meetingCurrIds = SectionMeeting::where('offering_id', $this->offering->id)
                ->pluck('curriculum_id')->unique()->filter()->values();
            if ($meetingCurrIds->isNotEmpty()) {
                $subjects = Curriculum::whereIn('id', $meetingCurrIds)->orderBy('course_code')->get();
            }
        }

        $this->specializationNames = class_exists(Specialization::class)
            ? Specialization::pluck('name','id')->all()
            : [];

        $roomTypeLabels = $this->roomTypeLabels();

        $this->plan = $subjects->map(function(Curriculum $s) use ($roomTypeLabels){
            $type = $this->detectType($s);
            [$specId, $specName] = $this->curriculumSpecialization($s);
            $rtl  = $this->curriculumRoomTypeLabel($s, $roomTypeLabels, $type);
            $unit = $this->subjectUnits($s, $type);

            return [
                'curriculum_id'     => $s->id,
                'code'              => $s->course_code,
                'title'             => $s->descriptive_title,
                'type'              => $type,
                'units'             => $unit,
                'specialization_id' => $specId,
                'specialization'    => $specName,
                'room_type_label'   => $rtl,
                'faculty_id'        => null,
                'room_id'           => null,
                'day'               => null,
                'start_time'        => null,
                'end_time'          => null,
                'inc'               => false,
                'inc_reason'        => null,
            ];
        })->values()->all();

        $this->planLoaded = true;

        $this->hydratePlanFromMeetings();
        $this->alreadyGenerated = SectionMeeting::where('offering_id',$this->offering->id)->exists();
        $this->recomputeCompleteness();
    }

    /** Detect most likely YEAR column in curricula */
    private function curriculaYearColumn(): ?string
    {
        $candidates = ['year_level','year','yr_level','level','year_name','yearlabel'];
        foreach ($candidates as $col) {
            if (Schema::hasColumn('curricula', $col)) return $col;
        }
        return null;
    }

    private function detectType(Curriculum $c): ?string
    {
        foreach (['type','component','class_type'] as $col) {
            if (Schema::hasColumn('curricula', $col) && !empty($c->{$col})) {
                return strtoupper((string)$c->{$col});
            }
        }
        foreach (['lab','lab_units','units_lab','has_lab'] as $col) {
            if (Schema::hasColumn('curricula', $col)) {
                $val = $c->{$col};
                if (is_bool($val) && $val) return 'LAB';
                if (is_numeric($val) && (int)$val > 0) return 'LAB';
                if (is_string($val) && trim($val) !== '' && strtoupper($val) !== '0' && strtoupper($val) !== 'NO') return 'LAB';
            }
        }
        return 'LEC';
    }

    private function curriculumSpecialization(Curriculum $c): array
    {
        if (Schema::hasColumn('curricula','specialization_id') && $c->specialization_id) {
            $id   = (int)$c->specialization_id;
            $name = $this->specializationNames[$id] ?? (Specialization::find($id)->name ?? null);
            return [$id, $name];
        }
        if (Schema::hasColumn('curricula','specialization') && $c->specialization) {
            $name = trim((string)$c->specialization);
            $id   = class_exists(Specialization::class) ? (int)(Specialization::where('name',$name)->value('id') ?? 0) : 0;
            return [$id ?: null, $name ?: null];
        }
        return [null, null];
    }

    private function curriculumRoomTypeLabel(Curriculum $c, array $roomTypeLabels, ?string $subjectType): string
    {
        foreach (['room_type_id','preferred_room_type_id','required_room_type_id'] as $col) {
            if (Schema::hasColumn('curricula',$col) && !empty($c->{$col})) {
                $id = (int)$c->{$col};
                return $roomTypeLabels[$id] ?? "Type #{$id}";
            }
        }
        foreach (['room_type','room_type_code'] as $col) {
            if (Schema::hasColumn('curricula',$col) && !empty($c->{$col})) {
                return (string)$c->{$col};
            }
        }
        if (strtoupper((string)$subjectType) === 'LAB') return 'LAB';
        if (Schema::hasColumn('curricula','lab') && (int)($c->lab ?? 0) > 0) return 'LAB';
        return 'LEC';
    }

    private function atCapacity(User $f): bool
    {
        $cur = (int)($this->facultyLoad[$f->id] ?? 0);
        $max = (int)($f->max_units ?? 0);
        return $max > 0 && $cur >= $max;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // TEXT NORMALIZATION + EXACT MATCHING
    // ─────────────────────────────────────────────────────────────────────────────
    private function normalizeSynonyms(string $t): string
    {
        $map = [
            'NSTP'          => 'NATIONAL SERVICE TRAINING PROGRAM',
            'PATH-FIT'      => 'PHYSICAL ACTIVITY TOWARDS HEALTH AND FITNESS',
            'PATHFIT'       => 'PHYSICAL ACTIVITY TOWARDS HEALTH AND FITNESS',
            'OOP'           => 'OBJECT ORIENTED PROGRAMMING',
            'GE '           => 'GENERAL EDUCATION ',
            'COMP PROG'     => 'COMPUTER PROGRAMMING',
            'COMP PROGRAM'  => 'COMPUTER PROGRAMMING',
            'PROGRAMING'    => 'PROGRAMMING',
            'INTRO TO'      => 'INTRODUCTION TO',
        ];
        $t = strtr($t, $map);
        $t = preg_replace('/\s+/', ' ', trim($t));
        return $t;
    }

    /**
     * Normalizes titles/spec names:
     * - Uppercase
     * - Replace known synonyms
     * - Remove parentheses content and digits
     * - Remove punctuation
     * - Collapse spaces
     * - Drop stopwords
     */
    private function normalizeKey(?string $text): string
    {
        if (!$text) return '';
        $t = strtoupper($text);
        $t = $this->normalizeSynonyms($t);
        $t = preg_replace('/\([^)]*\)|\[[^\]]*\]|\d+/', ' ', $t);
        $t = preg_replace('/[^A-Z0-9\s]/', ' ', $t);
        $t = preg_replace('/\s+/', ' ', trim($t));
        $t = preg_replace('/\s+(I{1,3}|IV|V|VI{0,3}|VII{0,1}|VIII|IX|X)\b$/', '', $t);
        $stop = [
            'LAB','LEC','INTRODUCTION','BASICS','FUNDAMENTALS','AND','OF','THE','IN','FOR',
            'WITH','TO','AN','A','PROGRAM','COURSE','GENERAL','EDUCATION'
        ];
        $words = array_values(array_filter(explode(' ', $t), fn($w) => $w !== '' && !in_array($w, $stop, true)));
        return trim(implode(' ', $words));
    }

    /** exact strict: normalized strings must be equal */
    private function exactTitleSpecMatch(string $currTitle, string $specName): bool
    {
        $T = $this->normalizeKey($currTitle);
        $S = $this->normalizeKey($specName);
        if ($T === '' || $S === '') return false;
        return $T === $S;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // AVAILABILITY (Part-Time only)
    // ─────────────────────────────────────────────────────────────────────────────
    private function isPartTime(User $f): bool
    {
        $status = optional($f->employment)->employment_status
            ?? DB::table('users_employments')->where('user_id',$f->id)->value('employment_status');
        return $status === 'Part-Time';
    }

    private function withinEndLimit(User $f, string $end): bool
    {
        // Compare HH:MM strings lexicographically
        return $this->isPartTime($f)
            ? ($end <= $this->PT_END_LIMIT)
            : ($end <= $this->FT_END_LIMIT);
    }

    private function loadFacultyAvailability(array $facultyIds): array
    {
        $out = [];
        if (empty($facultyIds)) return $out;
        if (!Schema::hasTable('faculty_availabilities') || !Schema::hasTable('time_slots')) return $out;

        $rows = DB::table('faculty_availabilities as fa')
            ->join('time_slots as ts', 'ts.id', '=', 'fa.time_slot_id')
            ->whereIn('fa.user_id', $facultyIds)
            ->where('fa.is_available', 1)
            ->select('fa.user_id','fa.day','ts.start_time','ts.end_time')
            ->orderBy('fa.user_id')->get();

        foreach ($rows as $r) {
            $uid = (int)$r->user_id;
            $day = (string)$r->day;
            $s = substr($r->start_time, 0, 5);
            $e = substr($r->end_time,   0, 5);
            $out[$uid][$day][] = [$s, $e];
        }
        return $out;
    }

    private function partTimeCoversWindow(User $f, string $day, string $start, string $end): bool
    {
        if (!$this->isPartTime($f)) {
            // Full-time: ignore availability table but respect end-limit
            return $this->withinEndLimit($f, $end);
        }

        // Part-time: strict availability AND end-limit
        $uid  = (int)$f->id;
        $wins = $this->facultyAvail[$uid][$day] ?? [];

        if (empty($wins)) {
            return $this->ENFORCE_PT_AVAIL_STRICT ? false : $this->withinEndLimit($f, $end);
        }

        foreach ($wins as [$s, $e]) {
            if ($s <= $start && $e >= $end) {
                return $this->withinEndLimit($f, $end);
            }
        }
        return false;
    }

    private function partTimeCoversPair(User $f, array $pair, string $start, string $end): bool
    {
        return $this->partTimeCoversWindow($f, $pair[0], $start, $end)
            && $this->partTimeCoversWindow($f, $pair[1], $start, $end);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // STRICT SPECIALIZATION ELIGIBILITY (EXACT)
    // ─────────────────────────────────────────────────────────────────────────────
    private function strictEligibleFaculty($faculty, ?Curriculum $curr)
    {
        $title = $curr ? ($curr->descriptive_title ?? $curr->course_code ?? '') : '';
        [$currSpecId, $currSpecName] = $curr ? $this->curriculumSpecialization($curr) : [null, null];

        // Filter: must have specialization(s)
        $pool = $faculty->filter(function(User $f){
            $specs = $this->facultySpecializations[$f->id] ?? [];
            return !empty($specs);
        })->values();

        if ($pool->isEmpty()) return $pool;

        if ($currSpecId || $currSpecName) {
            $currNameNorm = $this->normalizeKey((string)$currSpecName);

            $pool = $pool->filter(function(User $f) use ($currSpecId, $currNameNorm){
                $specIds = $this->facultySpecializations[$f->id] ?? [];
                $names   = array_map(fn($sid) => $this->specializationNames[$sid] ?? null, $specIds);
                $names   = array_values(array_filter($names));

                $idOk = $currSpecId ? in_array((int)$currSpecId, $specIds, true) : false;

                $nameOk = false;
                if (!$idOk && $currNameNorm !== '' && $names) {
                    foreach ($names as $nm) {
                        if ($this->exactTitleSpecMatch($currNameNorm, $nm)) { $nameOk = true; break; }
                    }
                }

                return $idOk || $nameOk;
            })->values();
        } else {
            // No explicit spec on curriculum -> title vs specialization exact equality
            $pool = $pool->filter(function(User $f) use ($title){
                $specIds = $this->facultySpecializations[$f->id] ?? [];
                foreach ($specIds as $sid) {
                    $nm = $this->specializationNames[$sid] ?? null;
                    if ($nm && $this->exactTitleSpecMatch($title, $nm)) return true;
                }
                return false;
            })->values();
        }

        // After strict filter, drop those at capacity
        $pool = $pool->reject(fn(User $f)=>$this->atCapacity($f))->values();

        // Rank: low current load then name
        $rank = function($coll){
            return $coll->map(function(User $f){
                    return (object)[
                        'f'    => $f,
                        'load' => (int)($this->facultyLoad[$f->id] ?? 0),
                        'name' => strtoupper((string)($f->name ?? 'ZZZ')),
                    ];
                })
                ->sortBy([['load','asc'],['name','asc']])
                ->values()
                ->map(fn($r)=>$r->f)
                ->values();
        };

        return $rank($pool);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // GENERATE (schedules + loads)
    // ─────────────────────────────────────────────────────────────────────────────
    public function generate(): void
    {
        $faculty = $this->teachingFaculty()->values();
        $rtIndex = $this->buildRoomTypeIndex();

        $this->facultySpecializations = $this->facultySpecMap($faculty->pluck('id')->all());
        $this->specializationNames    = class_exists(Specialization::class)
            ? (Specialization::pluck('name','id')->all())
            : [];
        $this->facultyLoad            = $this->facultyInitialLoads();
        $this->facultyAvail           = $this->loadFacultyAvailability($faculty->pluck('id')->all());

        $holdSection = [];  // [day => [[st,et],...]]
        $holdRoom    = [];  // [roomId => [day => [[st,et],...]]]
        $holdFaculty = [];  // [facultyId => [day => [[st,et],...]]]

        $batchInserts = []; // section_meetings
        $forLoads     = []; // rows to persist in faculty_loads
        $seen         = [];

        $created = 0; $skipped = 0;

        foreach ($this->plan as $idx => $row) {
            $dup = SectionMeeting::where('offering_id', $this->offering->id)
                ->where('curriculum_id', $row['curriculum_id'])
                ->exists();
            if ($dup) { $skipped++; continue; }

            $curr = Curriculum::find($row['curriculum_id']);
            if (!$curr) {
                $this->plan[$idx]['inc'] = true;
                $this->plan[$idx]['inc_reason'] = 'Curriculum not found';
                $skipped++;
                continue;
            }

            $unitsNeed = $this->subjectUnits($curr, $row['type']);
            $roomsPool = $this->candidateRoomsForCurriculum($curr, $rtIndex);

            // STRICT specialization-only exact eligibility
            $eligible = $this->STRICT_SPECIALIZATION_ONLY
                ? $this->strictEligibleFaculty($faculty, $curr)
                : collect();

            if ($eligible->isEmpty()) {
                $this->plan[$idx]['inc'] = true;
                $this->plan[$idx]['inc_reason'] = 'Strict specialization: no exact match';
                $skipped++;
                continue;
            }

            $isLab = strtoupper((string)$row['type']) === 'LAB';
            $scheduled = false;

            // LEC → prefer MW/TTh 1.5h pairs (now includes evening, but FT end-time capped)
            if (!$isLab) {
                foreach ($this->dayPairs as $pair) {
                    foreach ($this->slots as [$st,$et]) {
                        if ($this->blockedByPolicy($st, $et)) continue;

                        if ($this->conflictSection($pair[0], $st, $et, $this->offering->id) ||
                            $this->conflictSection($pair[1], $st, $et, $this->offering->id) ||
                            $this->hasSectionHold($holdSection, $pair[0], $st, $et) ||
                            $this->hasSectionHold($holdSection, $pair[1], $st, $et)) continue;

                        $roomPicked = null;
                        foreach ($roomsPool as $room) {
                            if ($this->conflictRoom($pair[0],$st,$et,$room->id) ||
                                $this->conflictRoom($pair[1],$st,$et,$room->id) ||
                                $this->hasRoomHold($holdRoom,$room->id,$pair[0],$st,$et) ||
                                $this->hasRoomHold($holdRoom,$room->id,$pair[1],$st,$et)) continue;
                            $roomPicked = $room; break;
                        }
                        if (!$roomPicked) continue;

                        // enforce PT availability + end-time limits per faculty
                        $availOk = $eligible->filter(function(User $f) use ($pair,$st,$et) {
                            return $this->partTimeCoversPair($f, $pair, $st, $et);
                        })->values();

                        if ($availOk->isEmpty()) {
                            $this->plan[$idx]['inc_reason'] = 'No faculty covering this MW/TTh window (PT avail or end-time limit)';
                            continue;
                        }

                        $noConflict = $availOk->reject(function(User $f) use ($pair,$st,$et,$holdFaculty){
                            return $this->conflictFaculty($pair[0],$st,$et,$f->id) ||
                                   $this->conflictFaculty($pair[1],$st,$et,$f->id) ||
                                   $this->hasFacultyHold($holdFaculty,$f->id,$pair[0],$st,$et) ||
                                   $this->hasFacultyHold($holdFaculty,$f->id,$pair[1],$st,$et);
                        })->values();

                        $underCap = $noConflict->filter(function(User $f) use ($unitsNeed,$et){
                            if (!$this->withinEndLimit($f, $et)) return false;
                            $cur = $this->facultyLoad[$f->id] ?? 0;
                            $max = (int)($f->max_units ?? 0);
                            return !($max > 0 && ($cur + $unitsNeed) > $max);
                        })->values();

                        $facultyPicked = $underCap->first();
                        if (!$facultyPicked) {
                            $this->plan[$idx]['inc_reason'] = 'All candidates conflicted/over max/end-time';
                            continue;
                        }

                        $pickedId = $facultyPicked->id;

                        $addedRows = 0;
                        foreach ($pair as $d) {
                            $addedRows += (int)$this->addBatchRow($batchInserts, $seen, [
                                'offering_id'   => $this->offering->id,
                                'curriculum_id' => $curr->id,
                                'faculty_id'    => $pickedId,
                                'room_id'       => $roomPicked->id,
                                'day'           => $d,
                                'start_time'    => $st,
                                'end_time'      => $et,
                                'type'          => $row['type'],
                                'notes'         => null,
                            ]);
                        }

                        if ($addedRows === 2) {
                            foreach ($pair as $d) {
                                $this->pushSectionHold($holdSection,$d,$st,$et);
                                $this->pushRoomHold($holdRoom,$roomPicked->id,$d,$st,$et);
                                $this->pushFacultyHold($holdFaculty,$pickedId,$d,$st,$et);
                            }

                            // reflect UI
                            $this->plan[$idx]['day']        = $pair[0].'/'.$pair[1];
                            $this->plan[$idx]['start_time'] = $st;
                            $this->plan[$idx]['end_time']   = $et;
                            $this->plan[$idx]['room_id']    = $roomPicked->id;
                            $this->plan[$idx]['faculty_id'] = $pickedId;
                            $this->plan[$idx]['inc']        = false;
                            $this->plan[$idx]['inc_reason'] = null;

                            $this->facultyLoad[$pickedId] =
                                ($this->facultyLoad[$pickedId] ?? 0) + $unitsNeed;

                            $forLoads[] = [
                                'faculty_id'    => $pickedId,
                                'curriculum_id' => $curr->id,
                                'type'          => $row['type'] ?? null,
                            ];

                            $created += 2;
                            $scheduled = true;
                            break 2;
                        }
                    }
                }
            }

            // Fallback: 3h block (FRI prioritized for LAB)
            if ($isLab || !$scheduled) {
                $eligible = $this->STRICT_SPECIALIZATION_ONLY
                    ? $this->strictEligibleFaculty($faculty, $curr)
                    : collect();

                if ($eligible->isEmpty()) {
                    $this->plan[$idx]['inc'] = true;
                    $this->plan[$idx]['inc_reason'] = 'Strict specialization: no exact match (3h fallback)';
                    $skipped++;
                    continue;
                }

                $daysOrder = $isLab
                    ? array_merge(['FRI'], ['MON','TUE','WED','THU'])
                    : ['FRI','MON','TUE','WED','THU'];

                $placed = false;
                foreach ($daysOrder as $d) {
                    foreach ($this->merged3hSlots() as [$st,$et]) {
                        if ($this->blockedByPolicy($st, $et)) continue;

                        if ($this->conflictSection($d,$st,$et,$this->offering->id) ||
                            $this->hasSectionHold($holdSection,$d,$st,$et)) continue;

                        $roomPicked = null;
                        foreach ($roomsPool as $room) {
                            if ($this->conflictRoom($d,$st,$et,$room->id) ||
                                $this->hasRoomHold($holdRoom,$room->id,$d,$st,$et)) continue;
                            $roomPicked = $room; break;
                        }
                        if (!$roomPicked) continue;

                        $availOk = $eligible->filter(function(User $f) use ($d,$st,$et) {
                            return $this->partTimeCoversWindow($f, $d, $st, $et);
                        })->values();

                        if ($availOk->isEmpty()) {
                            $this->plan[$idx]['inc_reason'] = "No faculty covering {$d} {$st}-{$et} (PT avail or end-time limit)";
                            continue;
                        }

                        $noConflict = $availOk->reject(function(User $f) use ($d,$st,$et,$holdFaculty){
                            return $this->conflictFaculty($d,$st,$et,$f->id) ||
                                   $this->hasFacultyHold($holdFaculty,$f->id,$d,$st,$et);
                        })->values();

                        $underCap = $noConflict->filter(function(User $f) use ($unitsNeed,$et){
                            if (!$this->withinEndLimit($f, $et)) return false;
                            $cur = $this->facultyLoad[$f->id] ?? 0;
                            $max = (int)($f->max_units ?? 0);
                            return !($max > 0 && ($cur + $unitsNeed) > $max);
                        })->values();

                        $facultyPicked = $underCap->first();
                        if (!$facultyPicked) {
                            $this->plan[$idx]['inc_reason'] = 'All candidates conflicted/over max/end-time (3h fallback)';
                            continue;
                        }

                        $pickedId = $facultyPicked->id;

                        if ($this->addBatchRow($batchInserts, $seen, [
                            'offering_id'   => $this->offering->id,
                            'curriculum_id' => $curr->id,
                            'faculty_id'    => $pickedId,
                            'room_id'       => $roomPicked->id,
                            'day'           => $d,
                            'start_time'    => $st,
                            'end_time'      => $et,
                            'type'          => $row['type'],
                            'notes'         => null,
                        ])) {
                            $this->pushSectionHold($holdSection,$d,$st,$et);
                            $this->pushRoomHold($holdRoom,$roomPicked->id,$d,$st,$et);
                            $this->pushFacultyHold($holdFaculty,$pickedId,$d,$st,$et);

                            $this->plan[$idx]['day']        = $d;
                            $this->plan[$idx]['start_time'] = $st;
                            $this->plan[$idx]['end_time']   = $et;
                            $this->plan[$idx]['room_id']    = $roomPicked->id;
                            $this->plan[$idx]['faculty_id'] = $pickedId;
                            $this->plan[$idx]['inc']        = false;
                            $this->plan[$idx]['inc_reason'] = null;

                            $this->facultyLoad[$pickedId] =
                                ($this->facultyLoad[$pickedId] ?? 0) + $unitsNeed;

                            $forLoads[] = [
                                'faculty_id'    => $pickedId,
                                'curriculum_id' => $curr->id,
                                'type'          => $row['type'] ?? null,
                            ];

                            $created++;
                            $scheduled = true;
                            $placed = true;
                            break 2;
                        }
                    }
                }

                if (!$placed) {
                    $this->plan[$idx]['inc'] = true;
                    if (!$this->plan[$idx]['inc_reason']) {
                        $this->plan[$idx]['inc_reason'] = 'No feasible 3h placement';
                    }
                    $skipped++;
                }
            }

            if (!$scheduled && !$isLab) {
                $this->plan[$idx]['inc'] = true;
                if (!$this->plan[$idx]['inc_reason']) {
                    $this->plan[$idx]['inc_reason'] = 'No feasible MW/TTh placement';
                }
                $skipped++;
            }
        }

        if (!empty($batchInserts)) {
            DB::transaction(function() use ($batchInserts, $forLoads) {
                SectionMeeting::insert($batchInserts);

                $headUserId = $this->headUserIdForOffering();
                $this->ensureAdminLoadForHeadUser($headUserId);

                $this->persistFacultyLoads($forLoads);
            });
        }

        $this->alreadyGenerated = SectionMeeting::where('offering_id',$this->offering->id)->exists();
        $this->hydratePlanFromMeetings();
        $this->recomputeCompleteness();

        $incCount = collect($this->plan)->where('inc', true)->count();
        if ($incCount > 0) {
            $reasons = collect($this->plan)->where('inc', true)->pluck('inc_reason')->filter()->unique()->values()->all();
            session()->flash('offerings_warning', "INC rows: {$incCount}. Reasons: ".implode(' | ', $reasons));
        }

        session()->flash('success', "Generated $created meeting(s). Skipped $skipped.");
        $this->dispatch('$refresh');
    }

    /** combine two consecutive 1.5h blocks into 3h windows, then drop lunch-overlapping */
    private function merged3hSlots(): array
    {
        $merged = [];
        for ($i=0; $i<count($this->slots)-1; $i++) {
            $merged[] = [$this->slots[$i][0], $this->slots[$i+1][1]];
        }
        return array_values(array_filter($merged, fn($w) => !$this->blockedByPolicy($w[0], $w[1])));
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CANCEL / REGENERATE / VIEW
    // ─────────────────────────────────────────────────────────────────────────────
    public function cancelSection(): void
    {
        if (!$this->alreadyGenerated) {
            session()->flash('offerings_warning', 'Nothing to cancel for this section.');
            $this->recomputeCompleteness();
            $this->dispatch('$refresh');
            return;
        }

        DB::transaction(function () {
            $meetings = SectionMeeting::where('offering_id', $this->offering->id)
                ->get(['faculty_id','curriculum_id']);

            SectionMeeting::where('offering_id', $this->offering->id)->delete();

            if (Schema::hasTable('faculty_loads')) {
                $deletedBySection = 0;

                if (Schema::hasColumn('faculty_loads','section')) {
                    $q = DB::table('faculty_loads')
                        ->where('section', (string)($this->offering->section_id ?? ''));
                    if (Schema::hasColumn('faculty_loads','academic_id') && isset($this->offering->academic_id)) {
                        $q->where('academic_id', (int)$this->offering->academic_id);
                    }
                    $deletedBySection = $q->delete();
                }

                if (!$deletedBySection && $meetings->isNotEmpty()) {
                    DB::table('faculty_loads')
                        ->whereIn('curriculum_id', $meetings->pluck('curriculum_id')->unique())
                        ->whereIn('user_id', $meetings->pluck('faculty_id')->filter()->unique())
                        ->when(
                            Schema::hasColumn('faculty_loads','academic_id') && isset($this->offering->academic_id),
                            fn($q) => $q->where('academic_id', (int)$this->offering->academic_id)
                        )
                        ->delete();
                }
            }
        });

        // Reset UI + caches
        $this->alreadyGenerated = false;

        foreach ($this->plan as $i => $row) {
            $this->plan[$i]['faculty_id'] = null;
            $this->plan[$i]['room_id']    = null;
            $this->plan[$i]['day']        = null;
            $this->plan[$i]['start_time'] = null;
            $this->plan[$i]['end_time']   = null;
            $this->plan[$i]['inc']        = false;
            $this->plan[$i]['inc_reason'] = null;
        }
        $this->facultyLoad = [];
        $this->facultySpecializations = [];
        $this->facultyAvail = [];

        $this->recomputeCompleteness();
        $this->loadPlan();
        $this->hydratePlanFromMeetings();
        $this->recomputeCompleteness();

        session()->flash('success', 'Section canceled. You can now click “Generate” to build a fresh schedule.');
        $this->dispatch('$refresh');
    }

    public function regenerateSection(): void
    {
        DB::transaction(function () {
            $meetings = SectionMeeting::where('offering_id', $this->offering->id)
                ->get(['faculty_id','curriculum_id']);

            SectionMeeting::where('offering_id', $this->offering->id)->delete();

            if (Schema::hasTable('faculty_loads')) {
                $deletedBySection = 0;

                if (Schema::hasColumn('faculty_loads','section')) {
                    $q = DB::table('faculty_loads')
                        ->where('section', (string)($this->offering->section_id ?? ''));
                    if (Schema::hasColumn('faculty_loads','academic_id') && isset($this->offering->academic_id)) {
                        $q->where('academic_id', (int)$this->offering->academic_id);
                    }
                    $deletedBySection = $q->delete();
                }

                if (!$deletedBySection && $meetings->isNotEmpty()) {
                    DB::table('faculty_loads')
                        ->whereIn('curriculum_id', $meetings->pluck('curriculum_id')->unique())
                        ->whereIn('user_id', $meetings->pluck('faculty_id')->filter()->unique())
                        ->when(
                            Schema::hasColumn('faculty_loads','academic_id') && isset($this->offering->academic_id),
                            fn($q) => $q->where('academic_id', (int)$this->offering->academic_id)
                        )
                        ->delete();
                }
            }
        });

        // Reset caches
        $this->alreadyGenerated = false;
        foreach ($this->plan as $i => $row) {
            $this->plan[$i]['faculty_id'] = null;
            $this->plan[$i]['room_id']    = null;
            $this->plan[$i]['day']        = null;
            $this->plan[$i]['start_time'] = null;
            $this->plan[$i]['end_time']   = null;
            $this->plan[$i]['inc']        = false;
            $this->plan[$i]['inc_reason'] = null;
        }
        $this->facultyLoad = [];
        $this->facultySpecializations = [];
        $this->facultyAvail = [];
        $this->recomputeCompleteness();

        $this->generate();

        session()->flash('success', 'Regenerated this section using the latest rules and availability.');
    }

    public function viewSection(): void
    {
        $this->redirectRoute('head.offerings.index');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ROOMS
    // ─────────────────────────────────────────────────────────────────────────────
    private function buildRoomTypeIndex(): array
    {
        $rooms  = Room::orderBy('code')->get();
        $labels = $this->roomTypeLabels(); // [id => UPPER(name)]
        $byId   = [];
        foreach ($rooms as $r) {
            $id = (int)($r->room_type_id ?? 0);
            $byId[$id] ??= [];
            $byId[$id][] = $r;
        }
        return ['byId' => $byId, 'labels' => $labels, 'all' => $rooms];
    }

    private function candidateRoomsForCurriculum(Curriculum $c, array $rtIndex): array
    {
        foreach (['room_type_id','preferred_room_type_id','required_room_type_id'] as $col) {
            if (Schema::hasColumn('curricula', $col) && !empty($c->{$col})) {
                $tId = (int)$c->{$col};
                if (!empty($rtIndex['byId'][$tId])) return $rtIndex['byId'][$tId];
            }
        }
        foreach (['room_type','room_type_code'] as $col) {
            if (Schema::hasColumn('curricula', $col) && !empty($c->{$col})) {
                $want = strtoupper((string)$c->{$col});
                $matchIds = array_keys(array_filter($rtIndex['labels'] ?? [], fn($lab) =>
                    $lab === $want || str_contains($lab, $want)
                ));
                $pool = [];
                foreach ($matchIds as $id) if (!empty($rtIndex['byId'][$id])) $pool = array_merge($pool, $rtIndex['byId'][$id]);
                if ($pool) return $pool;
            }
        }
        $isLab = ($this->detectType($c) === 'LAB') || (Schema::hasColumn('curricula','lab') && (int)($c->lab ?? 0) > 0);
        if (!empty($rtIndex['labels'])) {
            $pool = [];
            foreach ($rtIndex['byId'] as $id => $rooms) {
                $labish = isset($rtIndex['labels'][$id]) && str_contains($rtIndex['labels'][$id], 'LAB');
                if ($isLab ? $labish : !$labish) $pool = array_merge($pool, $rooms);
            }
            if ($pool) return $pool;
        }
        return $rtIndex['all'];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // DB CONFLICT CHECKS + IN-MEMORY HOLDS
    // ─────────────────────────────────────────────────────────────────────────────
    private function conflictRoom(string $day, string $start, string $end, int $roomId): bool
    {
        return SectionMeeting::where('day', $day)
            ->where('room_id', $roomId)
            ->where('start_time','<',$end)
            ->where('end_time','>',$start)
            ->exists();
    }
    private function conflictFaculty(string $day, string $start, string $end, int $facultyId): bool
    {
        return SectionMeeting::where('day', $day)
            ->where('faculty_id', $facultyId)
            ->where('start_time','<',$end)
            ->where('end_time','>',$start)
            ->exists();
    }
    private function conflictSection(string $day, string $start, string $end, int $offeringId): bool
    {
        return SectionMeeting::where('day', $day)
            ->where('offering_id', $offeringId)
            ->where('start_time','<',$end)
            ->where('end_time','>',$start)
            ->exists();
    }

    private function overlaps(string $aS, string $aE, string $bS, string $bE): bool
    { return ($aS < $bE) && ($aE > $bS); }

    private function blockedByPolicy(string $start, string $end): bool
    {
        foreach ($this->BLOCKED_WINDOWS as [$bs,$be]) {
            if ($this->overlaps($start, $end, $bs, $be)) return true;
        }
        return false;
    }

    private function hasSectionHold(array $h, string $d, string $s, string $e): bool
    { foreach (($h[$d] ?? []) as [$S,$E]) if ($this->overlaps($S,$E,$s,$e)) return true; return false; }
    private function hasRoomHold(array $h, int $rid, string $d, string $s, string $e): bool
    { foreach ((($h[$rid] ?? [])[$d] ?? []) as [$S,$E]) if ($this->overlaps($S,$E,$s,$e)) return true; return false; }
    private function hasFacultyHold(array $h, int $fid, string $d, string $s, string $e): bool
    { foreach ((($h[$fid] ?? [])[$d] ?? []) as [$S,$E]) if ($this->overlaps($S,$E,$s,$e)) return true; return false; }

    private function pushSectionHold(array &$h, string $d, string $s, string $e): void
    { $h[$d][] = [$s,$e]; }
    private function pushRoomHold(array &$h, int $rid, string $d, string $s, string $e): void
    { $h[$rid][$d][] = [$s,$e]; }
    private function pushFacultyHold(array &$h, int $fid, string $d, string $s, string $e): void
    { $h[$fid][$d][] = [$s,$e]; }

    private function addBatchRow(array &$batch, array &$seen, array $row): bool
    {
        $sig = implode('|', [
            (int)$row['curriculum_id'],
            (int)($row['room_id'] ?? 0),
            (int)($row['faculty_id'] ?? 0),
            (string)$row['day'],
            (string)$row['start_time'],
            (string)$row['end_time'],
            (string)($row['type'] ?? ''),
        ]);
        if (isset($seen[$sig])) return false;

        $exists = SectionMeeting::query()
            ->where('offering_id', $this->offering->id)
            ->where('curriculum_id', $row['curriculum_id'])
            ->where('day', $row['day'])
            ->where('start_time', $row['start_time'])
            ->where('end_time', $row['end_time'])
            ->where('type', $row['type'] ?? null)
            ->when(isset($row['room_id']), fn($q) => $q->where('room_id', $row['room_id']))
            ->when(!isset($row['room_id']), fn($q) => $q->whereNull('room_id'))
            ->when(isset($row['faculty_id']), fn($q) => $q->where('faculty_id', $row['faculty_id']))
            ->when(!isset($row['faculty_id']), fn($q) => $q->whereNull('faculty_id'))
            ->exists();

        if ($exists) return false;

        $batch[] = $row + ['created_at' => now(), 'updated_at' => now()];
        $seen[$sig] = true;
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // LOADS
    // ─────────────────────────────────────────────────────────────────────────────
    private function headUserIdForOffering(): ?int
    {
        if (Schema::hasTable('course_offerings') && Schema::hasColumn('course_offerings','head_user_id')) {
            $id = DB::table('course_offerings')->where('id', $this->offering->id)->value('head_user_id');
            if ($id) return (int)$id;
        }
        if (Schema::hasColumn('course_offerings','department_id') && Schema::hasColumn('users','department_id')) {
            $deptId = $this->offering->department_id ?? null;
            if ($deptId) {
                $uid = DB::table('users')
                    ->where('department_id', $deptId)
                    ->whereIn('role', ['Chairperson','Head','Dean','Program Head'])
                    ->orderBy('id','asc')
                    ->value('id');
                if ($uid) return (int)$uid;
            }
        }
        if (Schema::hasColumn('users','course_id') && isset($this->offering->course_id)) {
            $uid = DB::table('users')
                ->where('course_id', $this->offering->course_id)
                ->whereIn('role', ['Chairperson','Head','Dean','Program Head'])
                ->orderBy('id','asc')
                ->value('id');
            if ($uid) return (int)$uid;
        }
        $auth = Auth::user();
        if ($auth && in_array((string)($auth->role ?? ''), ['Chairperson','Head','Dean','Program Head'], true)) {
            return (int)$auth->id;
        }
        return null;
    }

    private function adminLoadRowIdForHead(?int $headUserId): ?int
    {
        if (!$headUserId || !Schema::hasTable('administrative_loads')) return null;

        $q = DB::table('administrative_loads')->where('user_id', $headUserId);
        if (Schema::hasColumn('administrative_loads','academic_id') && isset($this->offering->academic_id)) {
            $q->where('academic_id', $this->offering->academic_id);
        }
        $id = $q->orderByDesc('id')->value('id');
        return $id ? (int)$id : null;
    }

    private function facultyLoadAdminIdCol(): ?string
    {
        if (!Schema::hasTable('faculty_loads')) return null;
        if (Schema::hasColumn('faculty_loads','administrative_id')) return 'administrative_id';
        return null;
    }

    private function facultyLoadAdminLoadCol(): ?string
    {
        if (!Schema::hasTable('faculty_loads')) return null;
        if (Schema::hasColumn('faculty_loads','administrative_load_id')) return 'administrative_load_id';
        return null;
    }

    private function subjectUnits(Curriculum $c, ?string $type): int
    {
        if (Schema::hasColumn('curricula', 'units') && isset($c->units) && $c->units !== '') {
            return (int) $c->units;
        }
        return 0;
    }

    private function contactHours(Curriculum $c, ?string $type): int
    {
        return $this->subjectUnits($c, $type);
    }

    private function facultyInitialLoads(): array
    {
        $loads = [];

        $meetingsQ = SectionMeeting::query()
            ->select('section_meetings.*')
            ->whereNotNull('section_meetings.faculty_id');

        if (Schema::hasTable('course_offerings')
            && Schema::hasColumn('course_offerings','academic_id')
            && isset($this->offering->academic_id)) {

            $meetingsQ->join('course_offerings as co','co.id','=','section_meetings.offering_id')
                      ->where('co.academic_id', $this->offering->academic_id);
        } else {
            $meetingsQ->where('offering_id', $this->offering->id);
        }

        $meetings = $meetingsQ->with('curriculum')->get();

        foreach ($meetings as $m) {
            $uid = (int)$m->faculty_id;
            $units = ($m->relationLoaded('curriculum') && $m->curriculum)
                ? $this->subjectUnits($m->curriculum, $m->type)
                : 0;
            $loads[$uid] = ($loads[$uid] ?? 0) + $units;
        }

        if (Schema::hasTable('administrative_loads')) {
            $adminQ = DB::table('administrative_loads')
                ->select('user_id', DB::raw('COALESCE(SUM(units),0) as u'));

            if (Schema::hasColumn('administrative_loads','academic_id') && isset($this->offering->academic_id)) {
                $adminQ->where('academic_id', $this->offering->academic_id);
            }

            $admin = $adminQ->groupBy('user_id')->pluck('u','user_id')->all();
            foreach ($admin as $uid => $u) {
                $loads[(int)$uid] = ($loads[(int)$uid] ?? 0) + (int)$u;
            }
        }

        return $loads;
    }

    private function facultySpecMap(array $facultyIds): array
    {
        if (!Schema::hasTable('user_specializations')) return [];
        $rows = DB::table('user_specializations')
            ->select('user_id','specialization_id')
            ->whereIn('user_id', $facultyIds)
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->user_id][] = (int)$r->specialization_id;
        }
        foreach ($facultyIds as $id) {
            $map[(int)$id] = $map[(int)$id] ?? [];
        }
        return $map;
    }

    private function roleDefaultMaxUnits(?string $role): int
    {
        $r = strtoupper((string)$role);
        return in_array($r, ['CHAIRPERSON','HEAD','DEAN','PROGRAM HEAD'], true) ? 9 : 0;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // FACULTY POOL (Teaching)
    // ─────────────────────────────────────────────────────────────────────────────
    private function teachingFaculty()
    {
        // Base: from users_employments Teaching
        $faculty = User::query()
            ->whereExists(function($q){
                $q->select(DB::raw(1))
                  ->from('users_employments as ue')
                  ->whereColumn('ue.user_id','users.id')
                  ->where('ue.employment_classification','Teaching');
            })
            ->orderBy('name')
            ->get(['id','name','max_units','role','department_id','course_id']);

        // include head if missing
        $headId = $this->headUserIdForOffering();
        if ($headId && !$faculty->contains(fn($u)=>(int)$u->id===(int)$headId)) {
            if ($head = User::query()->find($headId,['id','name','max_units','role','department_id','course_id'])) {
                $faculty->push($head);
            }
        }

        // default max units when null (heads=9, others=0 meaning unlimited)
        return $faculty->map(function($u){
            $u->max_units = (int)($u->max_units ?: $this->roleDefaultMaxUnits($u->role));
            return $u;
        });
    }

    private function persistFacultyLoads(array $meetingRows): void
    {
        $adminIdCol     = $this->facultyLoadAdminIdCol();
        $adminLoadCol   = $this->facultyLoadAdminLoadCol();

        $headUserId     = $this->headUserIdForOffering();
        $headAdminRowId = $this->adminLoadRowIdForHead($headUserId);

        $byKey = [];
        foreach ($meetingRows as $r) {
            if (empty($r['faculty_id'])) continue;
            $curr = Curriculum::find($r['curriculum_id']);
            if (!$curr) continue;

            $userId  = (int)$r['faculty_id'];
            $units   = $this->subjectUnits($curr, $r['type'] ?? null);

            $payload = [
                'user_id'       => $userId,
                'curriculum_id' => (int)$curr->id,
                'contact_hours' => $units,
                'section'       => (string)($this->offering->section_id ?? ''),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $hasAcademicId = Schema::hasColumn('faculty_loads','academic_id') && isset($this->offering->academic_id);
            if ($hasAcademicId) {
                $payload['academic_id'] = (int)$this->offering->academic_id;
            }

            if ($adminIdCol && Schema::hasColumn('faculty_loads',$adminIdCol)) {
                $payload[$adminIdCol] = $headAdminRowId ?? null;
            }
            if ($adminLoadCol && Schema::hasColumn('faculty_loads',$adminLoadCol)) {
                $payload[$adminLoadCol] = $headAdminRowId ?? null;
            }

            $keyParts = [
                $payload['user_id'],
                $payload['curriculum_id'],
                $payload['section'],
            ];
            if ($hasAcademicId) {
                $keyParts[] = $payload['academic_id'];
            }
            if ($adminIdCol && array_key_exists($adminIdCol, $payload)) {
                $keyParts[] = (string)($payload[$adminIdCol] ?? '');
            }
            if ($adminLoadCol && array_key_exists($adminLoadCol, $payload)) {
                $keyParts[] = (string)($payload[$adminLoadCol] ?? '');
            }

            $key = implode('|', $keyParts);
            $byKey[$key] = $payload;
        }
        if (empty($byKey)) return;

        $toInsert = [];
        foreach ($byKey as $row) {
            $q = DB::table('faculty_loads')
                ->where('user_id', $row['user_id'])
                ->where('curriculum_id', $row['curriculum_id'])
                ->where('section', $row['section'] ?? '');

            if (Schema::hasColumn('faculty_loads','academic_id') && isset($row['academic_id'])) {
                $q->where('academic_id', $row['academic_id']);
            }

            $adminIdCol   = $this->facultyLoadAdminIdCol();
            $adminLoadCol = $this->facultyLoadAdminLoadCol();

            if ($adminIdCol && array_key_exists($adminIdCol, $row) && Schema::hasColumn('faculty_loads', $adminIdCol)) {
                $q->where($adminIdCol, $row[$adminIdCol]);
            }
            if ($adminLoadCol && array_key_exists($adminLoadCol, $row) && Schema::hasColumn('faculty_loads', $adminLoadCol)) {
                $q->where($adminLoadCol, $row[$adminLoadCol]);
            }

            if (!$q->exists()) {
                $toInsert[] = $row;
            }
        }

        if ($toInsert) {
            DB::table('faculty_loads')->insert($toInsert);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // HYDRATE UI FROM DB
    // ─────────────────────────────────────────────────────────────────────────────
    private function hydratePlanFromMeetings(): void
    {
        if (empty($this->plan)) return;

        $byCurr = [];
        foreach ($this->plan as $i => $row) {
            $byCurr[(int)$row['curriculum_id']] = $i;
        }

        $meetings = $this->offering->meetings()
            ->with(['curriculum','faculty','room'])
            ->orderByRaw("FIELD(day,'MON','TUE','WED','THU','FRI','SAT')")
            ->orderBy('start_time')
            ->get()
            ->groupBy('curriculum_id');

        foreach ($meetings as $currId => $set) {
            if (!isset($byCurr[$currId])) continue;
            $idx = $byCurr[$currId];

            $pair = null;
            $mw = $set->whereIn('day',['MON','WED'])->groupBy(fn($m)=>$m->start_time.'|'.$m->end_time)->first();
            if ($mw && count($mw) >= 2) $pair = ['days'=>'MON/WED','m'=>$mw->first()];

            if (!$pair) {
                $tt = $set->whereIn('day',['TUE','THU'])->groupBy(fn($m)=>$m->start_time.'|'.$m->end_time)->first();
                if ($tt && count($tt) >= 2) $pair = ['days'=>'TUE/THU','m'=>$tt->first()];
            }

            $m   = $pair['m'] ?? $set->first();
            $day = $pair['days'] ?? ($m?->day ?? null);

            $this->plan[$idx]['faculty_id'] = $m?->faculty_id;
            $this->plan[$idx]['room_id']    = $m?->room_id;
            $this->plan[$idx]['day']        = $day;
            $this->plan[$idx]['start_time'] = $m?->start_time;
            $this->plan[$idx]['end_time']   = $m?->end_time;
            $this->plan[$idx]['inc']        = false;
            $this->plan[$idx]['inc_reason'] = null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // COMPLETENESS
    // ─────────────────────────────────────────────────────────────────────────────
    private function recomputeCompleteness(): void
    {
        $missing = 0;
        foreach ($this->plan as $row) {
            if (empty($row['faculty_id'])) $missing++;
        }
        $this->incompleteCount = $missing;
        $this->hasUnassigned   = $missing > 0;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // UTIL
    // ─────────────────────────────────────────────────────────────────────────────
    private function yearAliases(?string $yl): array
    {
        if (!$yl) return [];
        $s = strtoupper(trim($yl));
        $map = [
            'FIRST YEAR'  => ['First Year','1st Year','Year 1','1'],
            'SECOND YEAR' => ['Second Year','2nd Year','Year 2','2'],
            'THIRD YEAR'  => ['Third Year','3rd Year','Year 3','3'],
            'FOURTH YEAR' => ['Fourth Year','4th Year','Year 4','4'],
            '1ST YEAR'    => ['First Year','1st Year','Year 1','1'],
            '2ND YEAR'    => ['Second Year','2nd Year','Year 2','2'],
            '3RD YEAR'    => ['Third Year','3rd Year','Year 3','3'],
            '4TH YEAR'    => ['Fourth Year','4th Year','Year 4','4'],
        ];
        return $map[$s] ?? [$yl];
    }

    private function semesterAliases($sem): array
    {
        if (!$sem) return [];
        $s = strtoupper(trim((string)$sem));
        $map = [
            '1'       => ['1','FIRST','FIRST SEM','FIRST SEMESTER','1ST','1ST SEM','1ST SEMESTER','SEM 1','SEMESTER 1'],
            '2'       => ['2','SECOND','SECOND SEM','SECOND SEMESTER','2ND','2ND SEM','2ND SEMESTER','SEM 2','SEMESTER 2'],
            'MIDYEAR' => ['MIDYEAR','MID-YEAR','MID YEAR','SUMMER','TERM 3','3'],
        ];
        foreach ($map as $key => $arr) {
            if (in_array($s, array_map('strtoupper',$arr), true)) {
                return $arr;
            }
        }
        return [$sem];
    }

    private function roomTypeLabels(): array
    {
        if (!Schema::hasTable('room_types')) return [];
        $labels = RoomType::pluck('name','id');
        return $labels->map(fn($v)=>strtoupper((string)$v))->all();
    }

    private function ensureAdminLoadForHeadUser(?int $headUserId): void
    {
        if (!$headUserId || !Schema::hasTable('administrative_loads')) return;

        $q = DB::table('administrative_loads')->where('user_id', $headUserId);
        $payload = [
            'user_id'    => $headUserId,
            'units'      => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('administrative_loads','academic_id') && isset($this->offering->academic_id)) {
            $q->where('academic_id', $this->offering->academic_id);
            $payload['academic_id'] = (int)$this->offering->academic_id;
        }

        // Guard possible NOT NULL columns
        if (Schema::hasColumn('administrative_loads','load_desc') && !isset($payload['load_desc'])) {
            $payload['load_desc'] = '';
        }

        if (!$q->exists()) {
            DB::table('administrative_loads')->insert($payload);
        }
    }

    public function render()
    {
        $faculty     = $this->teachingFaculty();
        $rooms       = Room::orderBy('code')->get();
        $totalUnits  = array_sum(array_map(fn($r) => (int)($r['units'] ?? 0), $this->plan ?? []));
        $this->alreadyGenerated = SectionMeeting::where('offering_id',$this->offering->id)->exists();

        $this->recomputeCompleteness();

        return view('livewire.head.schedulings.editor', compact('faculty','rooms','totalUnits'));
    }
}
