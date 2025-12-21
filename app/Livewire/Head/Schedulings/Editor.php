<?php

namespace App\Livewire\Head\Schedulings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use App\Services\Scheduling\GeneticScheduler;

use App\Models\{
    CourseOffering,
    Curriculum,
    SectionMeeting,
    Room,
    User,
    Specialization
};

#[Title('Edit Time & Room')]
#[Layout('layouts.head-shell')]
class Editor extends Component
{
    public CourseOffering $offering;

    public array $plan = [];
    public bool $planLoaded = false;
    public bool $alreadyGenerated = false;

    // GA INC reasons: curriculum_id => reason
    public array $incMap = [];

    // caches
    private array $specNames = [];

    /* ===================== MANUAL EDIT ===================== */

    public ?int $editingCid = null;

    // steps: FACULTY -> ROOM -> DAY -> TIME
    public string $pickStep = 'FACULTY';

    public array $edit = [
        'curriculum_id' => null,
        'faculty_id' => null,
        'room_id' => null,

        // Pair only for this flow (MON/WED, TUE/THU)
        'day_mode' => 'PAIR',
        'days' => ['MON','WED'],

        // time
        'time_slot_id' => null, // NOTE: now acts as "selected block index" (not DB time_slot_id)
        'start_time' => null,
        'end_time' => null,
    ];

    public array $timeSlots = [];          // [{id,start,end}, ...] (DB ranges)
    public array $facultyStatuses = [];    // [fid => ['ok'=>bool,'name'=>string,'reason'=>?string]]
    public array $roomPickStatuses = [];   // [rid => ['ok'=>bool,'code'=>string,'reason'=>?string]]
    public array $dayPickStatuses = [];    // ['MON/WED'=>['ok'=>bool,'reason'=>?string], ...]
    public array $timePickStatuses = [];   // [idx => ['ok'=>bool,'label'=>string,'reason'=>?string]]

    public bool $noRoomMatch = false;
    public ?string $editWarning = null;

    /** ✅ Scheduler display blocks */
    public array $pairBlocks = [];   // 90-min blocks
    public array $singleBlocks = []; // 180-min blocks

    /* ===================== MOUNT ===================== */

    public function mount(CourseOffering $offering)
    {
        $this->offering = $offering;
        $this->alreadyGenerated = SectionMeeting::where('offering_id', $offering->id)->exists();

        $this->loadPlan();
        $this->hydratePlanFromMeetings();

        // DB time slots used for availability windows (can be long ranges)
        if (Schema::hasTable('time_slots')) {
            $this->timeSlots = DB::table('time_slots')
                ->orderBy('start_time')
                ->get(['id','start_time','end_time'])
                ->map(fn($r) => [
                    'id' => (int)$r->id,
                    'start' => substr((string)$r->start_time, 0, 5),
                    'end'   => substr((string)$r->end_time,   0, 5),
                ])->all();
        }

        // ✅ class schedule blocks (what user should choose in TIME step)
        // PAIR = 90 mins per day (MON/WED or TUE/THU)
        $this->pairBlocks = [
            ['07:30','09:00'],
            ['09:00','10:30'],
            ['10:30','12:00'],
            ['13:00','14:30'],
            ['14:30','16:00'],
            ['16:00','17:30'],
            ['17:30','19:00'],
            ['19:00','20:30'],
        ];

        // SINGLE = 3 hours (one day)
        $this->singleBlocks = [
            ['07:30','10:30'],
            ['09:00','12:00'],
            ['13:00','16:00'],
            ['14:30','17:30'],
            ['16:00','19:00'],
            ['17:30','20:30'],
        ];
    }

    /* ===================== LOAD PLAN ===================== */

    public function loadPlan(): void
    {
        $this->specNames = class_exists(Specialization::class)
            ? Specialization::pluck('name', 'id')->all()
            : [];

        $q = Curriculum::query();

        if (Schema::hasColumn('curricula', 'course_id') && !empty($this->offering->course_id)) {
            $q->where('course_id', $this->offering->course_id);
        }

        $yearCol = $this->curriculaYearColumn();
        if ($yearCol && !empty($this->offering->year_level)) {
            $aliases = $this->yearAliases($this->offering->year_level);
            $q->where(function ($qq) use ($yearCol, $aliases) {
                foreach ($aliases as $a) {
                    $qq->orWhere($yearCol, 'like', $a)
                       ->orWhere($yearCol, 'like', "%{$a}%");
                }
            });
        }

        if (Schema::hasColumn('curricula', 'semester') && data_get($this->offering, 'academic.semester')) {
            $aliases = $this->semesterAliases(data_get($this->offering, 'academic.semester'));
            $q->where(function ($qq) use ($aliases) {
                foreach ($aliases as $a) {
                    $qq->orWhere('semester', 'like', $a)
                       ->orWhere('semester', 'like', "%{$a}%");
                }
            });
        }

        $subjects = $q->orderBy('course_code')->get();

        if ($subjects->isEmpty()) {
            $ids = SectionMeeting::where('offering_id', $this->offering->id)
                ->pluck('curriculum_id')
                ->unique()
                ->filter()
                ->values();

            if ($ids->isNotEmpty()) {
                $subjects = Curriculum::whereIn('id', $ids)->orderBy('course_code')->get();
            }
        }

        $this->plan = $subjects->map(function (Curriculum $c) {
            $lec  = (int)($c->lec ?? 0);
            $lab  = (int)($c->lab ?? 0);
            $type = ($lab > $lec) ? 'LAB' : 'LEC';

            $specId = (int)($c->specialization_id ?? 0);
            $specName = $specId > 0 ? ($this->specNames[$specId] ?? null) : null;

            return [
                'curriculum_id'     => (int)$c->id,
                'code'              => (string)($c->course_code ?? ''),
                'title'             => (string)($c->descriptive_title ?? ''),
                'units'             => (int)($c->units ?? 0),

                'type'              => $type,
                'specialization_id' => $specId ?: null,
                'specialization'    => $specName,

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

        $hasMeetings = SectionMeeting::where('offering_id', $this->offering->id)->exists();

        if ($hasMeetings) {
            $this->alreadyGenerated = true;
            $this->hydratePlanFromMeetings();

            if (!empty($this->incMap)) {
                foreach ($this->plan as $i => $row) {
                    $cid = (int)($row['curriculum_id'] ?? 0);
                    if ($cid > 0 && empty($row['faculty_id']) && isset($this->incMap[$cid])) {
                        $this->plan[$i]['inc'] = true;
                        $this->plan[$i]['inc_reason'] = (string)$this->incMap[$cid];
                    }
                }
            }
        } else {
            $this->alreadyGenerated = false;
            foreach ($this->plan as $i => $row) {
                $this->plan[$i]['inc'] = false;
                $this->plan[$i]['inc_reason'] = null;
            }
        }
    }

    /* ===================== GA ACTIONS ===================== */

    public function generate(): void
    {
        @set_time_limit(300);
        session()->forget(['success','offerings_warning']);

        $ga = new GeneticScheduler($this->offering, $this->plan, [
            'pop'   => 70,
            'gen'   => 160,
            'elite' => 12,
            'cx'    => 0.75,
            'mut'   => 0.20,
            'tk'    => 3,

            'STRICT_SPECIALIZATION_ONLY' => true,
            'ALLOW_IF_SUBJECT_NO_SPEC'   => false,
            'FALLBACK_IF_NO_MATCH'       => false,
            'ENFORCE_PT_AVAIL_STRICT'    => true,
        ]);

        $result = $ga->run();
        $this->incMap = $result['inc'] ?? [];

        if (empty($result['meetings'])) {
            session()->flash('offerings_warning', 'No meetings generated. Subjects may be INC (no eligible faculty / availability / max units / conflicts).');
            $this->hydratePlanFromMeetings();
            $this->dispatch('$refresh');
            return;
        }

        DB::transaction(function () use ($result) {
            SectionMeeting::where('offering_id', $this->offering->id)->delete();
            SectionMeeting::insert($result['meetings']);
        });

        $this->alreadyGenerated = true;
        $this->hydratePlanFromMeetings();

        if (!empty($this->incMap)) {
            session()->flash('offerings_warning', 'Generated matched subjects. Others are INC (no match/availability/units/room type/conflicts).');
        }

        session()->flash('success', 'Generated schedules successfully.');
        $this->dispatch('$refresh');
    }

    public function regenerateSection(): void
    {
        DB::transaction(function () {
            SectionMeeting::where('offering_id', $this->offering->id)->delete();
        });

        $this->alreadyGenerated = false;
        $this->incMap = [];

        foreach ($this->plan as $i => $row) {
            $this->plan[$i]['faculty_id'] = null;
            $this->plan[$i]['room_id'] = null;
            $this->plan[$i]['day'] = null;
            $this->plan[$i]['start_time'] = null;
            $this->plan[$i]['end_time'] = null;
            $this->plan[$i]['inc'] = false;
            $this->plan[$i]['inc_reason'] = null;
        }

        $this->generate();
    }

    public function cancelSection(): void
    {
        DB::transaction(function () {
            SectionMeeting::where('offering_id', $this->offering->id)->delete();
        });

        $this->alreadyGenerated = false;
        $this->incMap = [];

        foreach ($this->plan as $i => $row) {
            $this->plan[$i]['faculty_id'] = null;
            $this->plan[$i]['room_id'] = null;
            $this->plan[$i]['day'] = null;
            $this->plan[$i]['start_time'] = null;
            $this->plan[$i]['end_time'] = null;
            $this->plan[$i]['inc'] = false;
            $this->plan[$i]['inc_reason'] = null;
        }

        session()->flash('success', 'Section canceled.');
        $this->dispatch('$refresh');
    }

    /* ===================== HYDRATE PLAN FROM MEETINGS + INC ===================== */

    private function hydratePlanFromMeetings(): void
    {
        if (empty($this->plan)) return;

        $index = [];
        foreach ($this->plan as $i => $row) {
            $index[(int)($row['curriculum_id'] ?? 0)] = $i;
        }

        foreach ($this->plan as $i => $row) {
            $this->plan[$i]['faculty_id'] = null;
            $this->plan[$i]['room_id'] = null;
            $this->plan[$i]['day'] = null;
            $this->plan[$i]['start_time'] = null;
            $this->plan[$i]['end_time'] = null;
            $this->plan[$i]['inc'] = false;
            $this->plan[$i]['inc_reason'] = null;
        }

        $meetings = SectionMeeting::where('offering_id', $this->offering->id)
            ->get()
            ->groupBy('curriculum_id');

        foreach ($meetings as $cid => $set) {
            $cid = (int)$cid;
            if (!isset($index[$cid])) continue;

            $idx = $index[$cid];
            $m = $set->first();

            $days = $set->pluck('day')
                ->filter()
                ->map(fn($d) => strtoupper(trim((string)$d)))
                ->unique()
                ->values()
                ->all();
            sort($days);

            $dayStr = count($days) > 1 ? implode('/', $days) : ($days[0] ?? null);

            $this->plan[$idx]['faculty_id'] = $m?->faculty_id;
            $this->plan[$idx]['room_id']    = $m?->room_id;
            $this->plan[$idx]['day']        = $dayStr;
            $this->plan[$idx]['start_time'] = $m?->start_time ? substr((string)$m->start_time,0,5) : null;
            $this->plan[$idx]['end_time']   = $m?->end_time   ? substr((string)$m->end_time,0,5) : null;
        }

        foreach ($this->incMap as $cid => $reason) {
            $cid = (int)$cid;
            if (!isset($index[$cid])) continue;

            $idx = $index[$cid];
            if (empty($this->plan[$idx]['faculty_id'])) {
                $this->plan[$idx]['inc'] = true;
                $this->plan[$idx]['inc_reason'] = (string)$reason;
            }
        }
    }

    /* ===================== MANUAL EDIT: ONLY FOR ASSIGNED SUBJECTS ===================== */

    public function openManual(int $cid): void
    {
        $row = collect($this->plan)->firstWhere('curriculum_id', $cid);

        if (empty($row['faculty_id']) || empty($row['day']) || empty($row['start_time']) || empty($row['end_time'])) {
            session()->flash('offerings_warning', 'Cannot manual edit: This subject has no assigned faculty/schedule (INC).');
            return;
        }

        $this->editingCid = $cid;
        $this->pickStep = 'FACULTY';
        $this->editWarning = null;
        $this->noRoomMatch = false;

        $this->edit['curriculum_id'] = $cid;
        $this->edit['faculty_id'] = (int)$row['faculty_id'];
        $this->edit['room_id'] = (int)$row['room_id'];

        $dayStr = strtoupper((string)($row['day'] ?? ''));

        if ($dayStr && str_contains($dayStr, '/')) {
            $parts = array_map('trim', explode('/', $dayStr));
            $this->edit['day_mode'] = 'PAIR';
            $this->edit['days'] = [$this->normalizeDayKey($parts[0] ?? 'MON'), $this->normalizeDayKey($parts[1] ?? 'WED')];
        } else {
            $this->edit['day_mode'] = 'SINGLE';
            $this->edit['days'] = [$this->normalizeDayKey($dayStr ?: 'MON')];
        }

        $this->edit['start_time'] = $row['start_time'] ? substr((string)$row['start_time'],0,5) : null;
        $this->edit['end_time']   = $row['end_time'] ? substr((string)$row['end_time'],0,5) : null;

        // block index guess (optional)
        $this->edit['time_slot_id'] = $this->guessBlockIndex($this->edit['start_time'], $this->edit['end_time'], strtoupper($this->edit['day_mode']));

        $this->computeFacultyStatuses();
    }

    public function closeManual(): void
    {
        $this->editingCid = null;
        $this->pickStep = 'FACULTY';

        $this->facultyStatuses = [];
        $this->roomPickStatuses = [];
        $this->dayPickStatuses = [];
        $this->timePickStatuses = [];

        $this->noRoomMatch = false;
        $this->editWarning = null;
    }

    private function computeFacultyStatuses(): void
    {
        $cid = (int)($this->editingCid ?? 0);
        if ($cid <= 0) { $this->facultyStatuses = []; return; }

        $row = collect($this->plan)->firstWhere('curriculum_id', $cid);
        $specId = (int)($row['specialization_id'] ?? 0);

        if ($specId <= 0 || !Schema::hasTable('user_specializations')) {
            $this->facultyStatuses = [];
            return;
        }

        $maxCol = null;
        foreach (['max_units','max_load_units','allowed_units','units','max_unit'] as $c) {
            if (Schema::hasColumn('users', $c)) { $maxCol = $c; break; }
        }

        $hasProfile = Schema::hasTable('faculty_profiles')
            && Schema::hasColumn('faculty_profiles', 'user_id')
            && Schema::hasColumn('faculty_profiles', 'max_units');

        $userMaxExpr = $maxCol ? "NULLIF(users.{$maxCol},0)" : "NULL";
        $maxExpr = $hasProfile
            ? "COALESCE(NULLIF(fp.max_units,0), {$userMaxExpr}, 0)"
            : "COALESCE({$userMaxExpr}, 0)";

        $q = DB::table('users')
            ->join('user_specializations as us', 'us.user_id', '=', 'users.id')
            ->where('us.specialization_id', $specId)
            ->select([
                'users.id',
                'users.name',
                DB::raw("{$maxExpr} as max_units_final"),
            ])
            ->distinct()
            ->orderBy('users.name');

        if ($hasProfile) {
            $q->leftJoin('faculty_profiles as fp', 'fp.user_id', '=', 'users.id');
        }

        $faculty = $q->get();
        $loads = $this->currentTermLoadsMap();

        $out = [];
        foreach ($faculty as $f) {
            $fid  = (int)$f->id;
            $name = (string)$f->name;

            $max = (int)($f->max_units_final ?? 0);
            $cur = (int)($loads[$fid] ?? 0);

            $note = null;
            if ($max <= 0) {
                $max = 21;
                $note = "Max units not found → default {$max}";
            }

            if ($cur >= $max) {
                $out[$fid] = ['ok' => false, 'name' => $name, 'reason' => "Loaded {$cur}/{$max} units"];
                continue;
            }

            if (!$this->hasAnyAvailabilityDb($fid)) {
                $out[$fid] = ['ok' => false, 'name' => $name, 'reason' => "No availability set (faculty_availabilities)"];
                continue;
            }

            $out[$fid] = ['ok' => true, 'name' => $name, 'reason' => $note];
        }

        $this->facultyStatuses = $out;
    }

    private function hasAnyAvailabilityDb(int $facultyId): bool
    {
        if (!Schema::hasTable('faculty_availabilities')) return false;

        return DB::table('faculty_availabilities')
            ->where('user_id', $facultyId)
            ->where('is_available', 1)
            ->exists();
    }

    public function pickFaculty(int $fid): void
    {
        if (!($this->facultyStatuses[$fid]['ok'] ?? false)) return;

        $this->edit['faculty_id'] = $fid;

        $this->computeRoomsForFaculty();

        if ($this->noRoomMatch) {
            $this->pickStep = 'FACULTY';
            $this->editWarning = 'All rooms are red for this faculty (no possible match). Choose another green faculty.';
            return;
        }

        $this->pickStep = 'ROOM';
        $this->editWarning = null;
    }

    private function computeRoomsForFaculty(): void
    {
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $cid = (int)($this->editingCid ?? 0);

        $this->roomPickStatuses = [];
        $this->noRoomMatch = true;

        if ($fid<=0 || $cid<=0) return;

        $row = collect($this->plan)->firstWhere('curriculum_id', $cid);
        $type = strtoupper((string)($row['type'] ?? 'LEC'));

        $rooms = $this->candidateRoomsForType($type);

        foreach ($rooms as $r) {
            $rid = (int)$r->id;

            $ok = $this->existsAnyValidDayTime($rid, $fid);
            $this->roomPickStatuses[$rid] = [
                'ok' => $ok,
                'code' => (string)$r->code,
                'reason' => $ok ? null : 'No common free day/time with selected faculty',
            ];

            if ($ok) $this->noRoomMatch = false;
        }
    }

    public function pickRoom(int $rid): void
    {
        if (!($this->roomPickStatuses[$rid]['ok'] ?? false)) return;

        $this->edit['room_id'] = $rid;
        $this->computeDaysForRoomAndFaculty();

        $this->pickStep = 'DAY';
        $this->editWarning = null;
    }

    private function computeDaysForRoomAndFaculty(): void
    {
        $rid = (int)($this->edit['room_id'] ?? 0);
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        $this->dayPickStatuses = [];
        if ($rid<=0 || $fid<=0) return;

        if ($mode === 'SINGLE') {
            foreach (['MON','TUE','WED','THU','FRI','SAT'] as $day) {
                $day = $this->normalizeDayKey($day);
                $wins = $this->facultyAvailWindows($fid, $day);

                $ok = false;
                foreach ($this->singleBlocks as $b) {
                    [$st,$et] = $b;

                    if (!$this->coversAnyWindow($wins, $st, $et)) continue;

                    $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, (int)$this->editingCid);
                    if (!$conf['room'] && !$conf['faculty']) { $ok = true; break; }
                }

                $this->dayPickStatuses[$day] = [
                    'ok' => $ok,
                    'reason' => $ok ? null : 'No free time for this day',
                ];
            }
            return;
        }

        foreach ([['MON','WED'], ['TUE','THU']] as $pair) {
            $p1 = $this->normalizeDayKey($pair[0]);
            $p2 = $this->normalizeDayKey($pair[1]);

            $key = "{$p1}/{$p2}";
            $ok = false;

            foreach ($this->pairBlocks as $b) {
                [$st,$et] = $b;

                if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) continue;

                $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, (int)$this->editingCid);
                $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, (int)$this->editingCid);

                if (!$c1['room'] && !$c1['faculty'] && !$c2['room'] && !$c2['faculty']) { $ok = true; break; }
            }

            $this->dayPickStatuses[$key] = [
                'ok' => $ok,
                'reason' => $ok ? null : 'No common free time for both days',
            ];
        }
    }

    public function pickDay(string $key): void
    {
        $key = strtoupper(trim((string)$key));

        if (!($this->dayPickStatuses[$key]['ok'] ?? false)) {
            $this->editWarning = $this->dayPickStatuses[$key]['reason'] ?? 'Selected day is not allowed.';
            return;
        }

        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        if ($mode === 'PAIR') {
            $parts = array_map('trim', explode('/', $key));
            if (count($parts) < 2) {
                $this->editWarning = "Invalid pair '{$key}'. Please choose MON/WED or TUE/THU.";
                return;
            }
            $this->edit['days'] = [$this->normalizeDayKey($parts[0]), $this->normalizeDayKey($parts[1])];
        } else {
            $this->edit['days'] = [$this->normalizeDayKey($key)];
        }

        $this->edit['time_slot_id'] = null;
        $this->edit['start_time'] = null;
        $this->edit['end_time'] = null;

        $this->computeTimesForSelectedDay();

        $this->pickStep = 'TIME';
        $this->editWarning = null;
    }

    /**
     * ✅ FIXED: show class blocks (not DB time_slots ranges) and mark green/red based on:
     * - availability coverage windows
     * - conflicts (room/faculty)
     */
    private function computeTimesForSelectedDay(): void
    {
        $rid  = (int)($this->edit['room_id'] ?? 0);
        $fid  = (int)($this->edit['faculty_id'] ?? 0);
        $days = $this->edit['days'] ?? [];
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        $this->timePickStatuses = [];
        if ($rid<=0 || $fid<=0 || empty($days)) return;

        if ($mode === 'SINGLE') {
            $day = $this->normalizeDayKey((string)($days[0] ?? 'MON'));
            $wins = $this->facultyAvailWindows($fid, $day);

            foreach ($this->singleBlocks as $i => $b) {
                [$st,$et] = $b;

                if (!$this->coversAnyWindow($wins, $st, $et)) {
                    $this->timePickStatuses[$i] = [
                        'ok' => false,
                        'label' => "{$st}–{$et}",
                        'reason' => 'Not covered by faculty availability (DB)',
                    ];
                    continue;
                }

                $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, (int)$this->editingCid);
                if ($conf['faculty']) {
                    $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> "Faculty conflict {$day} {$st}-{$et}"];
                    continue;
                }
                if ($conf['room']) {
                    $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> "Room conflict {$day} {$st}-{$et}"];
                    continue;
                }

                $this->timePickStatuses[$i] = ['ok'=>true,'label'=> "{$st}–{$et}",'reason'=> null];
            }

            return;
        }

        // PAIR MODE
        $p1 = $this->normalizeDayKey((string)($days[0] ?? 'MON'));
        $p2 = $this->normalizeDayKey((string)($days[1] ?? 'WED'));

        foreach ($this->pairBlocks as $i => $b) {
            [$st,$et] = $b;

            if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) {
                $this->timePickStatuses[$i] = [
                    'ok' => false,
                    'label' => "{$st}–{$et}",
                    'reason' => 'Not covered by faculty availability for BOTH days',
                ];
                continue;
            }

            $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, (int)$this->editingCid);
            $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, (int)$this->editingCid);

            if ($c1['faculty'] || $c2['faculty']) {
                $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Faculty conflict on one of the pair days'];
                continue;
            }
            if ($c1['room'] || $c2['room']) {
                $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Room conflict on one of the pair days'];
                continue;
            }

            $this->timePickStatuses[$i] = ['ok'=>true,'label'=> "{$st}–{$et}",'reason'=> null];
        }
    }

    // Step 4 click (now uses "block index")
    public function pickTime(int $idx): void
    {
        if (!($this->timePickStatuses[$idx]['ok'] ?? false)) return;

        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        if ($mode === 'SINGLE') {
            [$st,$et] = $this->singleBlocks[$idx] ?? [null,null];
        } else {
            [$st,$et] = $this->pairBlocks[$idx] ?? [null,null];
        }

        if (!$st || !$et) return;

        $this->edit['time_slot_id'] = $idx; // internal selection only
        $this->edit['start_time'] = $st;
        $this->edit['end_time']   = $et;
        $this->editWarning = null;
    }

    // Final save
    public function saveManual(): void
    {
        $cid = (int)($this->editingCid ?? 0);
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $rid = (int)($this->edit['room_id'] ?? 0);
        $sel = (int)($this->edit['time_slot_id'] ?? 0);

        if ($cid <= 0 || $fid <= 0 || $rid <= 0 || $sel < 0) {
            $this->editWarning = 'Please pick Faculty → Room → Day → Time (all green).';
            return;
        }

        if (!($this->timePickStatuses[$sel]['ok'] ?? false)) {
            $this->editWarning = $this->timePickStatuses[$sel]['reason'] ?? 'Selected time is not valid.';
            return;
        }

        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        $days = $this->edit['days'] ?? ($mode === 'SINGLE' ? ['MON'] : ['MON','WED']);
        $days = array_values(array_filter(array_map(fn($d) => $this->normalizeDayKey((string)$d), $days)));

        if (empty($days)) {
            $this->editWarning = 'Please select day(s).';
            return;
        }

        $st = substr((string)($this->edit['start_time'] ?? ''), 0, 5);
        $et = substr((string)($this->edit['end_time'] ?? ''), 0, 5);

        if ($st === '' || $et === '') {
            $this->editWarning = 'Please select a time.';
            return;
        }

        // HARD RE-CHECK BEFORE SAVE
        foreach ($days as $d) {
            $conf = $this->checkConflictRoomFaculty($rid, $fid, $d, $st, $et, $cid);

            if (!empty($conf['faculty'])) {
                $this->editWarning = "Cannot save: Faculty conflict on {$d} {$st}-{$et}.";
                return;
            }
            if (!empty($conf['room'])) {
                $this->editWarning = "Cannot save: Room conflict on {$d} {$st}-{$et}.";
                return;
            }
        }

        // duration check based on block type (not DB time_slots)
        $mins = $this->slotMinutes($st, $et);
        $required = ($mode === 'SINGLE') ? 180 : 90;
        if ($mins !== $required) {
            $this->editWarning = "Invalid duration: selected slot is {$mins} mins. Required {$required} mins for {$mode}.";
            return;
        }

        DB::transaction(function () use ($cid, $fid, $rid, $days, $st, $et) {
            SectionMeeting::where('offering_id', (int)$this->offering->id)
                ->where('curriculum_id', $cid)
                ->delete();

            $rows = [];
            foreach ($days as $d) {
                $rows[] = [
                    'offering_id'   => (int)$this->offering->id,
                    'curriculum_id' => $cid,
                    'faculty_id'    => $fid,
                    'room_id'       => $rid,
                    'day'           => strtoupper((string)$d),
                    'start_time'    => $st,
                    'end_time'      => $et,
                    'type'          => null,
                    'notes'         => 'Manual edit',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            SectionMeeting::insert($rows);
        });

        unset($this->incMap[$cid]);

        $this->hydratePlanFromMeetings();
        $this->alreadyGenerated = SectionMeeting::where('offering_id', (int)$this->offering->id)->exists();

        session()->flash('success', 'Manual schedule saved successfully.');
        $this->closeManual();
        $this->dispatch('$refresh');
    }

    public function backToFaculty(): void
    {
        $this->pickStep = 'FACULTY';
        $this->editWarning = null;

        $this->roomPickStatuses = [];
        $this->dayPickStatuses = [];
        $this->timePickStatuses = [];

        $this->noRoomMatch = false;

        $this->computeFacultyStatuses();
    }

    /* ===================== FEASIBILITY HELPERS ===================== */

    private function existsAnyValidDayTime(int $rid, int $fid): bool
    {
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        if ($mode === 'SINGLE') {
            foreach (['MON','TUE','WED','THU','FRI','SAT'] as $day) {
                $day = $this->normalizeDayKey($day);
                $wins = $this->facultyAvailWindows($fid, $day);
                if (empty($wins)) continue;

                foreach ($this->singleBlocks as $b) {
                    [$st,$et] = $b;

                    if (!$this->coversAnyWindow($wins, $st, $et)) continue;

                    $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, (int)$this->editingCid);
                    if (!$conf['room'] && !$conf['faculty']) return true;
                }
            }
            return false;
        }

        foreach ([['MON','WED'], ['TUE','THU']] as $pair) {
            $p1 = $this->normalizeDayKey($pair[0]);
            $p2 = $this->normalizeDayKey($pair[1]);

            foreach ($this->pairBlocks as $b) {
                [$st,$et] = $b;

                if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) continue;

                $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, (int)$this->editingCid);
                $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, (int)$this->editingCid);

                if (!$c1['room'] && !$c1['faculty'] && !$c2['room'] && !$c2['faculty']) return true;
            }
        }

        return false;
    }

    /**
     * Conflicts are scoped to same academic_id (if exists) and excludes current (offering_id + curriculum_id).
     * ✅ day matching now supports aliases (MON/MONDAY etc)
     */
    private function checkConflictRoomFaculty(int $roomId, int $facultyId, string $day, string $start, string $end, int $excludeCurriculumId): array
    {
        $aliases = $this->dayAliasesUpper($day);

        $q = DB::table('section_meetings as sm')
            ->join('course_offerings as co', 'co.id', '=', 'sm.offering_id')
            ->whereIn(DB::raw('UPPER(sm.day)'), $aliases)
            ->whereNotNull('sm.start_time')
            ->whereNotNull('sm.end_time')
            ->whereRaw("(? < sm.end_time) AND (? > sm.start_time)", [$start, $end]);

        if (Schema::hasColumn('course_offerings','academic_id') && isset($this->offering->academic_id)) {
            $q->where('co.academic_id', (int)$this->offering->academic_id);
        }

        $q->where(function($qq) use ($excludeCurriculumId) {
            $qq->whereRaw('NOT (sm.offering_id = ? AND sm.curriculum_id = ?)', [
                (int)$this->offering->id,
                (int)$excludeCurriculumId
            ]);
        });

        $roomConflict = (clone $q)->where('sm.room_id', $roomId)->exists();
        $facultyConflict = (clone $q)->where('sm.faculty_id', $facultyId)->exists();

        return ['room' => $roomConflict, 'faculty' => $facultyConflict];
    }

    private function candidateRoomsForType(string $type)
    {
        if (Schema::hasTable('room_types') && Schema::hasColumn('rooms','room_type_id')) {
            if ($type === 'LAB') {
                $labTypeId = DB::table('room_types')->whereRaw("UPPER(name) LIKE '%LAB%'")->value('id');
                return $labTypeId ? Room::where('room_type_id',(int)$labTypeId)->orderBy('code')->get(['id','code']) : collect();
            }

            $lecTypeId = DB::table('room_types')->whereRaw("UPPER(name) LIKE '%LEC%' OR UPPER(name) LIKE '%CLASS%' OR UPPER(name) LIKE '%ROOM%'")->value('id');
            return $lecTypeId
                ? Room::where('room_type_id',(int)$lecTypeId)->orderBy('code')->get(['id','code'])
                : Room::orderBy('code')->get(['id','code']);
        }

        return Room::orderBy('code')->get(['id','code']);
    }

    private function currentTermLoadsMap(): array
    {
        $q = DB::table('section_meetings as sm')
            ->join('course_offerings as co','co.id','=','sm.offering_id')
            ->join('curricula as cu','cu.id','=','sm.curriculum_id')
            ->whereNotNull('sm.faculty_id');

        if (Schema::hasColumn('course_offerings','academic_id') && isset($this->offering->academic_id)) {
            $q->where('co.academic_id', (int)$this->offering->academic_id);
        }

        $rows = $q->get(['sm.faculty_id','cu.units']);
        $map = [];
        foreach ($rows as $r) {
            $fid = (int)$r->faculty_id;
            $map[$fid] = ($map[$fid] ?? 0) + (int)($r->units ?? 0);
        }
        return $map;
    }

    private function guessSlotId(?string $s, ?string $e): ?int
    {
        // legacy; not used for manual selection now (we use blocks)
        if (!$s || !$e) return null;
        foreach ($this->timeSlots as $ts) {
            if ($ts['start'] === $s && $ts['end'] === $e) return (int)$ts['id'];
        }
        return null;
    }

    private function guessBlockIndex(?string $s, ?string $e, string $mode): ?int
    {
        if (!$s || !$e) return null;
        $mode = strtoupper($mode);

        $list = $mode === 'SINGLE' ? $this->singleBlocks : $this->pairBlocks;
        foreach ($list as $i => $b) {
            if (($b[0] ?? null) === $s && ($b[1] ?? null) === $e) return (int)$i;
        }
        return null;
    }

    /* ===================== YEAR/SEM HELPERS ===================== */

    private function curriculaYearColumn(): ?string
    {
        foreach (['year_level','year','yr_level','level'] as $col) {
            if (Schema::hasColumn('curricula', $col)) return $col;
        }
        return null;
    }

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
            '1' => ['1','FIRST','FIRST SEM','FIRST SEMESTER','1ST','1ST SEM','1ST SEMESTER','SEM 1','SEMESTER 1'],
            '2' => ['2','SECOND','SECOND SEM','SECOND SEMESTER','2ND','2ND SEM','2ND SEMESTER','SEM 2','SEMESTER 2'],
            'MIDYEAR' => ['MIDYEAR','MID-YEAR','MID YEAR','SUMMER','TERM 3','3'],
        ];
        foreach ($map as $arr) {
            $upper = array_map('strtoupper', $arr);
            if (in_array($s, $upper, true)) return $arr;
        }
        return [$sem];
    }

    /* ===================== ✅ DAY NORMALIZATION + AVAILABILITY WINDOWS ===================== */

    private function normalizeDayKey(string $day): string
    {
        $d = strtoupper(trim($day));

        return match ($d) {
            'MONDAY', 'MON' => 'MON',
            'TUESDAY', 'TUE', 'TUES' => 'TUE',
            'WEDNESDAY', 'WED' => 'WED',
            'THURSDAY', 'THU', 'THURS' => 'THU',
            'FRIDAY', 'FRI' => 'FRI',
            'SATURDAY', 'SAT' => 'SAT',
            'SUNDAY', 'SUN' => 'SUN',
            default => $d,
        };
    }

    private function dayAliasesUpper(string $dayKey): array
    {
        $k = $this->normalizeDayKey($dayKey);

        return match ($k) {
            'MON' => ['MON', 'MONDAY'],
            'TUE' => ['TUE', 'TUES', 'TUESDAY'],
            'WED' => ['WED', 'WEDNESDAY'],
            'THU' => ['THU', 'THURS', 'THURSDAY'],
            'FRI' => ['FRI', 'FRIDAY'],
            'SAT' => ['SAT', 'SATURDAY'],
            'SUN' => ['SUN', 'SUNDAY'],
            default => [$k],
        };
    }

    /**
     * Return availability windows for a faculty on a given day.
     * Uses faculty_availabilities.time_slot_id -> time_slots.start_time/end_time.
     * These windows can be long ranges (e.g., 07:30-18:00) and that's OK.
     */
    private function facultyAvailWindows(int $facultyId, string $day): array
    {
        if (!Schema::hasTable('faculty_availabilities') || !Schema::hasTable('time_slots')) return [];

        $aliases = $this->dayAliasesUpper($day);

        $rows = DB::table('faculty_availabilities as fa')
            ->join('time_slots as ts', 'ts.id', '=', 'fa.time_slot_id')
            ->where('fa.user_id', $facultyId)
            ->where('fa.is_available', 1)
            ->whereIn(DB::raw('UPPER(fa.day)'), $aliases)
            ->get(['ts.start_time','ts.end_time']);

        return $rows->map(fn($r) => [
            substr((string)$r->start_time, 0, 5),
            substr((string)$r->end_time,   0, 5),
        ])->values()->all();
    }

    private function coversAnyWindow(array $windows, string $start, string $end): bool
    {
        foreach ($windows as $w) {
            $ws = $w[0] ?? null;
            $we = $w[1] ?? null;
            if (!$ws || !$we) continue;

            if ($ws <= $start && $we >= $end) return true;
        }
        return false;
    }

    private function coversPairWindows(int $facultyId, array $pairDays, string $start, string $end): bool
    {
        $d1 = $this->normalizeDayKey((string)($pairDays[0] ?? 'MON'));
        $d2 = $this->normalizeDayKey((string)($pairDays[1] ?? 'WED'));

        $w1 = $this->facultyAvailWindows($facultyId, $d1);
        $w2 = $this->facultyAvailWindows($facultyId, $d2);

        return $this->coversAnyWindow($w1, $start, $end)
            && $this->coversAnyWindow($w2, $start, $end);
    }

    private function slotMinutes(string $start, string $end): int
    {
        [$sh,$sm] = array_map('intval', explode(':', $start));
        [$eh,$em] = array_map('intval', explode(':', $end));
        return (($eh*60 + $em) - ($sh*60 + $sm));
    }

    public function setModePair(): void
    {
        $this->edit['day_mode'] = 'PAIR';
        $this->edit['days'] = ['MON','WED'];
        $this->computeDaysForRoomAndFaculty();
        $this->timePickStatuses = [];
    }

    public function setModeSingle(): void
    {
        $this->edit['day_mode'] = 'SINGLE';
        $this->edit['days'] = ['MON'];
        $this->computeDaysForRoomAndFaculty();
        $this->timePickStatuses = [];
    }

    public function render()
    {
        $faculty = User::orderBy('name')->get(['id','name']);
        $rooms   = Room::orderBy('code')->get(['id','code']);

        $totalUnits = 0;
        foreach (($this->plan ?? []) as $r) $totalUnits += (int)($r['units'] ?? 0);

        return view('livewire.head.schedulings.editor', compact('faculty','rooms','totalUnits'));
    }
}
