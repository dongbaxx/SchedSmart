<?php

namespace App\Livewire\Head\Schedulings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

use Illuminate\Support\Facades\Auth;
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

#[Title('Schedules - All Sections')]
#[Layout('layouts.head-shell')]
class Editor extends Component
{
    public int $academic;

    // display
    public array $allSections = []; // each: ['offering_id','year_level','section','term','program','plan'=>[]]

    // GA INC reasons per offering: [offering_id => [curriculum_id => reason]]
    public array $incByOffering = [];

    private array $specNames = [];

    public bool $isGenerating = false;
    public bool $canGenerate = true;     // enabled if there are empties
    public bool $isComplete = false;     // true if nothing empty

    /* ===================== MANUAL EDIT WIZARD STATE ===================== */
    public ?int $editingOfferingId = null;
    public ?int $editingCid = null;

    // steps: FACULTY -> ROOM -> DAY -> TIME
    public string $pickStep = 'FACULTY';
    public ?string $editWarning = null;

    // edit payload used by modal
    public array $edit = [
        'day_mode'     => 'PAIR',   // PAIR or SINGLE
        'faculty_id'   => null,
        'room_id'      => null,
        'days'         => [],       // PAIR: ['MON','WED'] OR ['TUE','THU'] | SINGLE: ['MON']
        'time_slot_id' => null,     // acts as "block index" (NOT DB time_slot_id)
        'start_time'   => null,
        'end_time'     => null,
    ];

    // statuses used by modal UI
    public array $facultyStatuses = [];   // [fid => ['ok'=>bool,'name'=>..,'reason'=>..]]
    public array $roomPickStatuses = [];  // [rid => ['ok'=>bool,'code'=>..,'reason'=>..]]
    public array $dayPickStatuses = [];   // ['MON/WED'=>['ok'=>bool,'reason'=>..], ...]
    public array $timePickStatuses = [];  // [idx => ['ok'=>bool,'label'=>..,'reason'=>..]]

    public bool $noRoomMatch = false;

    /** ✅ DB time slots used only for availability windows (can be long ranges) */
    public array $timeSlots = []; // [{id,start,end},...]

    /** ✅ UI blocks (what user chooses in TIME step) */
    public array $pairBlocks = [];   // 90-min
    public array $singleBlocks = []; // 180-min

    /* ===================== CACHE ===================== */
    private array $termOfferingIdsCache = [];

    public function mount(int $academic): void
    {
        $this->academic = $academic;

        $this->specNames = class_exists(Specialization::class)
            ? Specialization::pluck('name', 'id')->all()
            : [];

        // DB time slots for availability windows
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

        // ✅ schedule blocks
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

        $this->singleBlocks = [
            ['07:30','10:30'],
            ['09:00','12:00'],
            ['13:00','16:00'],
            ['14:30','17:30'],
            ['16:00','19:00'],
            ['17:30','20:30'],
        ];

        $this->loadAllSections();
    }

    /* ===================== MAIN ACTION: GENERATE ALL ===================== */

    public function generateAll(): void
    {
        if ($this->isGenerating) return;

        $this->isGenerating = true;

        try {
            @set_time_limit(600);

            $user = Auth::user();

            $offerings = CourseOffering::query()
                ->with(['academic','section'])
                ->where('academic_id', $this->academic)
                ->where('course_id', (int)$user->course_id)
                ->where('status', 'locked')
                ->get();

            if ($offerings->isEmpty()) {
                session()->flash('offerings_warning', 'No approved/locked sections found for this term.');
                return;
            }

            session()->forget(['success','offerings_warning']);

            $ids = $offerings->pluck('id')->all();

            // pull existing meetings once
            $existingMeetings = SectionMeeting::whereIn('offering_id', $ids)->get();

            // group: [offering_id][curriculum_id] => rows[]
            $existingGrouped = [];
            foreach ($existingMeetings as $m) {
                $existingGrouped[(int)$m->offering_id][(int)$m->curriculum_id][] = $m;
            }

            $this->incByOffering = [];

            foreach ($offerings as $offering) {

                $fullPlan = $this->buildPlanFor($offering);
                $existingByCurr = $existingGrouped[(int)$offering->id] ?? [];

                // ✅ Determine which curricula are incomplete
                $incompleteCids = [];
                foreach ($fullPlan as $row) {
                    $cid = (int)($row['curriculum_id'] ?? 0);
                    if (!$cid) continue;

                    $rows = $existingByCurr[$cid] ?? [];
                    if (!$this->meetingIsComplete($rows)) {
                        $incompleteCids[] = $cid;
                    }
                }

                if (empty($incompleteCids)) {
                    $this->incByOffering[(int)$offering->id] = [];
                    continue;
                }

                // ✅ Delete ONLY incomplete meetings
                SectionMeeting::where('offering_id', (int)$offering->id)
                    ->whereIn('curriculum_id', $incompleteCids)
                    ->delete();

                // ✅ Plan for GA = only incomplete subjects
                $planForGA = collect($fullPlan)->filter(function ($row) use ($incompleteCids) {
                    $cid = (int)($row['curriculum_id'] ?? 0);
                    return in_array($cid, $incompleteCids, true);
                })->values()->all();

                $ga = new GeneticScheduler($offering, $planForGA, [
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

                $this->incByOffering[(int)$offering->id] = $result['inc'] ?? [];

                if (!empty($result['meetings'])) {
                    SectionMeeting::insert($result['meetings']);
                }
            }

            $this->loadAllSections(); // also recompute button state

            if ($this->isComplete) {
                session()->flash('success', 'Generated successfully. All sections are now complete.');
            } else {
                session()->flash('offerings_warning', 'Generated incomplete subjects only. Some remain INC/unfilled.');
            }

            $this->dispatch('$refresh');

        } finally {
            $this->isGenerating = false;
        }
    }

    /* ===================== LOAD ALL SECTIONS FOR DISPLAY ===================== */

    private function loadAllSections(): void
    {
        $user = Auth::user();

        $offerings = CourseOffering::query()
            ->with(['academic','section','course'])
            ->where('academic_id', $this->academic)
            ->where('course_id', (int)$user->course_id)
            ->where('status', 'locked')
            ->get()
            ->sort(function ($a, $b) {
                $ya = $this->yearRank($a->year_level ?? '');
                $yb = $this->yearRank($b->year_level ?? '');
                if ($ya !== $yb) return $ya <=> $yb;

                $sa = strtoupper($a->section?->section_name ?? '');
                $sb = strtoupper($b->section?->section_name ?? '');
                return $sa <=> $sb;
            })
            ->values();

        $ids = $offerings->pluck('id')->all();

        $meetings = SectionMeeting::whereIn('offering_id', $ids)->get();

        $meetingsGrouped = [];
        foreach ($meetings as $m) {
            $oid = (int)$m->offering_id;
            $cid = (int)$m->curriculum_id;
            $meetingsGrouped[$oid][$cid][] = $m;
        }

        $out = [];

        foreach ($offerings as $off) {
            $plan = $this->buildPlanFor($off);
            $plan = $this->hydratePlanForOffering((int)$off->id, $plan, $meetingsGrouped);

            $incMap = $this->incByOffering[(int)$off->id] ?? [];
            foreach ($plan as $i => $row) {
                $cid = (int)($row['curriculum_id'] ?? 0);
                if ($cid && empty($row['faculty_id']) && isset($incMap[$cid])) {
                    $plan[$i]['inc_reason'] = (string)$incMap[$cid];
                }
            }

            $program =
                data_get($off,'course.name')
                ?? data_get($off,'course.course_name')
                ?? data_get($off,'course.program_name')
                ?? data_get($off,'course.title')
                ?? data_get($off,'course.course')
                ?? data_get($off,'course.abbr')
                ?? data_get($off,'course.code')
                ?? data_get($off,'course.course_code')
                ?? '—';

            $out[] = [
                'offering_id' => (int)$off->id,
                'year_level'  => (string)($off->year_level ?? '—'),
                'section'     => (string)($off->section?->section_name ?? '—'),
                'term'        => (string)(data_get($off,'academic.school_year','—').' — '.data_get($off,'academic.semester','—')),
                'program'     => (string)$program,
                'plan'        => $plan,
            ];
        }

        $this->allSections = $out;
        $this->recomputeGenerateState();
    }

    /* ===================== PLAN BUILDING ===================== */

    private function yearRank(string $yl): int
    {
        $s = strtoupper(trim((string)$yl));
        return match (true) {
            str_contains($s,'FIRST')  || in_array($s,['1','1ST'],true) => 1,
            str_contains($s,'SECOND') || in_array($s,['2','2ND'],true) => 2,
            str_contains($s,'THIRD')  || in_array($s,['3','3RD'],true) => 3,
            str_contains($s,'FOURTH') || in_array($s,['4','4TH'],true) => 4,
            default => 99,
        };
    }

    private function curriculaYearColumn(): ?string
    {
        foreach (['year_level','year','yr_level','level'] as $col) {
            if (DB::getSchemaBuilder()->hasColumn('curricula', $col)) return $col;
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

    private function buildPlanFor(CourseOffering $offering): array
    {
        $q = Curriculum::query();

        if (DB::getSchemaBuilder()->hasColumn('curricula', 'course_id') && !empty($offering->course_id)) {
            $q->where('course_id', $offering->course_id);
        }

        $yearCol = $this->curriculaYearColumn();
        if ($yearCol && !empty($offering->year_level)) {
            $aliases = $this->yearAliases($offering->year_level);
            $q->where(function ($qq) use ($yearCol, $aliases) {
                foreach ($aliases as $a) {
                    $qq->orWhere($yearCol, 'like', $a)
                       ->orWhere($yearCol, 'like', "%{$a}%");
                }
            });
        }

        if (DB::getSchemaBuilder()->hasColumn('curricula', 'semester') && data_get($offering, 'academic.semester')) {
            $aliases = $this->semesterAliases(data_get($offering, 'academic.semester'));
            $q->where(function ($qq) use ($aliases) {
                foreach ($aliases as $a) {
                    $qq->orWhere('semester', 'like', $a)
                       ->orWhere('semester', 'like', "%{$a}%");
                }
            });
        }

        $subjects = $q->orderBy('course_code')->get();

        return $subjects->map(function (Curriculum $c) {
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

                // ✅ keep BOTH so manual can filter properly
                'specialization_id' => $specId ?: null,
                'specialization'    => $specName,

                'faculty_id' => null,
                'room_id'    => null,
                'day'        => null,
                'start_time' => null,
                'end_time'   => null,

                'inc_reason' => null,
            ];
        })->values()->all();
    }

    private function hydratePlanForOffering(int $offeringId, array $plan, array $meetingsGrouped): array
    {
        $idx = [];
        foreach ($plan as $i => $row) {
            $idx[(int)($row['curriculum_id'] ?? 0)] = $i;
        }

        $byCurr = $meetingsGrouped[$offeringId] ?? [];

        foreach ($byCurr as $cid => $rows) {
            $cid = (int)$cid;
            if (!isset($idx[$cid])) continue;

            $i = $idx[$cid];
            $first = $rows[0] ?? null;

            $days = collect($rows)->pluck('day')->filter()
                ->map(fn($d)=>strtoupper(trim((string)$d)))
                ->unique()->values()->all();

            sort($days);
            $dayStr = count($days) > 1 ? implode('/', $days) : ($days[0] ?? null);

            $plan[$i]['faculty_id'] = $first->faculty_id ?? null;
            $plan[$i]['room_id']    = $first->room_id ?? null;
            $plan[$i]['day']        = $dayStr;
            $plan[$i]['start_time'] = $first->start_time ? substr((string)$first->start_time, 0, 5) : null;
            $plan[$i]['end_time']   = $first->end_time   ? substr((string)$first->end_time,   0, 5) : null;
        }

        return $plan;
    }

    /* ===================== HELPERS: TERM OFFERINGS ===================== */

    private function termOfferingIds(): array
    {
        if (!empty($this->termOfferingIdsCache)) return $this->termOfferingIdsCache;

        $user = Auth::user();

        $this->termOfferingIdsCache = CourseOffering::query()
            ->where('academic_id', $this->academic)
            ->where('course_id', (int)$user->course_id)
            ->where('status', 'locked')
            ->pluck('id')
            ->map(fn($x)=>(int)$x)
            ->all();

        return $this->termOfferingIdsCache;
    }

    /* ===================== ✅ DAY NORMALIZATION + ALIASES ===================== */

    private function normalizeDay(string $day): string
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
        $k = $this->normalizeDay($dayKey);

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

    /* ===================== ✅ AVAILABILITY WINDOWS (faculty_availabilities + time_slots) ===================== */

    private function hasAnyAvailabilityDb(int $facultyId): bool
    {
        if (!Schema::hasTable('faculty_availabilities')) return false;

        return DB::table('faculty_availabilities')
            ->where('user_id', $facultyId)
            ->where('is_available', 1)
            ->exists();
    }

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
        $d1 = $this->normalizeDay((string)($pairDays[0] ?? 'MON'));
        $d2 = $this->normalizeDay((string)($pairDays[1] ?? 'WED'));

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

    /* ===================== ✅ CONFLICT CHECK (TERM SCOPED + exclude current) ===================== */

    private function checkConflictRoomFaculty(int $roomId, int $facultyId, string $day, string $start, string $end, int $excludeOfferingId, int $excludeCurriculumId): array
    {
        $aliases = $this->dayAliasesUpper($day);

        $q = DB::table('section_meetings as sm')
            ->join('course_offerings as co', 'co.id', '=', 'sm.offering_id')
            ->where('co.academic_id', (int)$this->academic)
            ->whereIn(DB::raw('UPPER(sm.day)'), $aliases)
            ->whereNotNull('sm.start_time')
            ->whereNotNull('sm.end_time')
            ->whereRaw("(? < sm.end_time) AND (? > sm.start_time)", [$start, $end])
            ->whereRaw('NOT (sm.offering_id = ? AND sm.curriculum_id = ?)', [$excludeOfferingId, $excludeCurriculumId]);

        $roomConflict = (clone $q)->where('sm.room_id', $roomId)->exists();
        $facultyConflict = (clone $q)->where('sm.faculty_id', $facultyId)->exists();

        return ['room' => $roomConflict, 'faculty' => $facultyConflict];
    }

    /* ===================== ✅ LOADS (for max units rule) ===================== */

    private function currentTermLoadsMap(): array
    {
        $rows = DB::table('section_meetings as sm')
            ->join('course_offerings as co', 'co.id', '=', 'sm.offering_id')
            ->join('curricula as cu', 'cu.id', '=', 'sm.curriculum_id')
            ->where('co.academic_id', (int)$this->academic)
            ->whereNotNull('sm.faculty_id')
            // ✅ one row per subject assignment (PAIR rows collapse)
            ->select('sm.faculty_id', 'sm.offering_id', 'sm.curriculum_id', 'cu.units')
            ->groupBy('sm.faculty_id', 'sm.offering_id', 'sm.curriculum_id', 'cu.units')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $fid = (int)$r->faculty_id;
            $map[$fid] = ($map[$fid] ?? 0) + (int)($r->units ?? 0);
        }
        return $map;
    }

    /* ===================== MANUAL EDIT: OPEN/CLOSE ===================== */

    public function openManual(int $offeringId, int $curriculumId): void
    {
        $this->editingOfferingId = $offeringId;
        $this->editingCid = $curriculumId;

        $this->editWarning = null;
        $this->pickStep = 'FACULTY';
        $this->noRoomMatch = false;

        // reset edit selections
        $this->edit = [
            'day_mode'     => 'PAIR',
            'faculty_id'   => null,
            'room_id'      => null,
            'days'         => ['MON','WED'],
            'time_slot_id' => null,
            'start_time'   => null,
            'end_time'     => null,
        ];

        // read current schedule
        $rows = SectionMeeting::query()
            ->where('offering_id', $offeringId)
            ->where('curriculum_id', $curriculumId)
            ->get();

        if ($rows->isNotEmpty()) {
            $first = $rows->first();

            $days = $rows->pluck('day')->map(fn($d)=>$this->normalizeDay((string)$d))->unique()->values()->all();
            sort($days);

            $this->edit['faculty_id'] = $first->faculty_id;
            $this->edit['room_id']    = $first->room_id;

            if (count($days) > 1) {
                $this->edit['day_mode'] = 'PAIR';
                $this->edit['days'] = $days;
            } else {
                $this->edit['day_mode'] = 'SINGLE';
                $this->edit['days'] = [$days[0] ?? 'MON'];
            }

            $this->edit['start_time'] = $first->start_time ? substr((string)$first->start_time,0,5) : null;
            $this->edit['end_time']   = $first->end_time   ? substr((string)$first->end_time,0,5) : null;

            // guess block index
            $this->edit['time_slot_id'] = $this->guessBlockIndex(
                $this->edit['start_time'],
                $this->edit['end_time'],
                strtoupper((string)$this->edit['day_mode'])
            );
        }

        // ✅ Step 1 statuses (SPECIALIZATION FILTER FIX)
        $this->computeFacultyStatusesFiltered($curriculumId);

        $this->roomPickStatuses = [];
        $this->dayPickStatuses = [];
        $this->timePickStatuses = [];
    }

    public function closeManual(): void
    {
        $this->editingOfferingId = null;
        $this->editingCid = null;
        $this->editWarning = null;
        $this->pickStep = 'FACULTY';

        $this->facultyStatuses = [];
        $this->roomPickStatuses = [];
        $this->dayPickStatuses = [];
        $this->timePickStatuses = [];

        $this->noRoomMatch = false;
    }

    public function backToFaculty(): void
    {
        $this->pickStep = 'FACULTY';
        $this->editWarning = null;

        $this->roomPickStatuses = [];
        $this->dayPickStatuses = [];
        $this->timePickStatuses = [];

        $this->noRoomMatch = false;

        $cid = (int)($this->editingCid ?? 0);
        if ($cid > 0) $this->computeFacultyStatusesFiltered($cid);
    }

    /* ===================== MANUAL EDIT: MODE ===================== */

    public function setModePair(): void
    {
        $this->edit['day_mode'] = 'PAIR';
        $this->edit['days'] = ['MON','WED'];

        $this->edit['time_slot_id'] = null;
        $this->edit['start_time'] = null;
        $this->edit['end_time'] = null;

        if (!empty($this->edit['room_id']) && !empty($this->edit['faculty_id'])) {
            $this->computeDaysForRoomAndFaculty();
        }
        $this->timePickStatuses = [];
    }

    public function setModeSingle(): void
    {
        $this->edit['day_mode'] = 'SINGLE';
        $this->edit['days'] = ['MON'];

        $this->edit['time_slot_id'] = null;
        $this->edit['start_time'] = null;
        $this->edit['end_time'] = null;

        if (!empty($this->edit['room_id']) && !empty($this->edit['faculty_id'])) {
            $this->computeDaysForRoomAndFaculty();
        }
        $this->timePickStatuses = [];
    }

    /* ===================== ✅ MANUAL: FACULTY STATUS (FILTER BY SPECIALIZATION) ===================== */

    private function computeFacultyStatusesFiltered(int $curriculumId): void
    {
        $this->facultyStatuses = [];

        $c = Curriculum::find($curriculumId);
        if (!$c) return;

        $specId = (int)($c->specialization_id ?? 0);

        if ($specId <= 0 || !Schema::hasTable('user_specializations')) {
            // strict specialization-only behavior
            return;
        }

        // max units column detection
        $maxCol = null;
        foreach (['max_units','max_load_units','allowed_units','units','max_unit'] as $col) {
            if (Schema::hasColumn('users', $col)) { $maxCol = $col; break; }
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
                $this->facultyStatuses[$fid] = ['ok'=>false,'name'=>$name,'reason'=>"Loaded {$cur}/{$max} units"];
                continue;
            }

            if (!$this->hasAnyAvailabilityDb($fid)) {
                $this->facultyStatuses[$fid] = ['ok'=>false,'name'=>$name,'reason'=>"No availability set (faculty_availabilities)"];
                continue;
            }

            $this->facultyStatuses[$fid] = ['ok'=>true,'name'=>$name,'reason'=>$note];
        }
    }

    /* ===================== MANUAL EDIT: PICK STEPS ===================== */

    public function pickFaculty(int $fid): void
    {
        if (!($this->facultyStatuses[$fid]['ok'] ?? false)) return;

        $this->editWarning = null;
        $this->edit['faculty_id'] = $fid;

        $this->computeRoomsForFaculty();

        if ($this->noRoomMatch) {
            $this->pickStep = 'FACULTY';
            $this->editWarning = 'All rooms are red for this faculty (no possible match). Choose another green faculty.';
            return;
        }

        $this->pickStep = 'ROOM';
    }

    private function candidateRoomsForType(string $type)
    {
        if (Schema::hasTable('room_types') && Schema::hasColumn('rooms','room_type_id')) {
            if ($type === 'LAB') {
                $labTypeId = DB::table('room_types')->whereRaw("UPPER(name) LIKE '%LAB%'")->value('id');
                return $labTypeId
                    ? Room::where('room_type_id',(int)$labTypeId)->orderBy('code')->get(['id','code'])
                    : collect();
            }

            $lecTypeId = DB::table('room_types')->whereRaw("UPPER(name) LIKE '%LEC%' OR UPPER(name) LIKE '%CLASS%' OR UPPER(name) LIKE '%ROOM%'")->value('id');
            return $lecTypeId
                ? Room::where('room_type_id',(int)$lecTypeId)->orderBy('code')->get(['id','code'])
                : Room::orderBy('code')->get(['id','code']);
        }

        return Room::orderBy('code')->get(['id','code']);
    }

    private function computeRoomsForFaculty(): void
    {
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $cid = (int)($this->editingCid ?? 0);

        $this->roomPickStatuses = [];
        $this->noRoomMatch = true;

        if ($fid<=0 || $cid<=0) return;

        $type = 'LEC';
        $c = Curriculum::find($cid);
        if ($c) {
            $lec  = (int)($c->lec ?? 0);
            $lab  = (int)($c->lab ?? 0);
            $type = ($lab > $lec) ? 'LAB' : 'LEC';
        }

        $rooms = $this->candidateRoomsForType(strtoupper($type));

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

        $this->editWarning = null;
        $this->edit['room_id'] = $rid;

        $this->computeDaysForRoomAndFaculty();
        $this->pickStep = 'DAY';
    }

    private function computeDaysForRoomAndFaculty(): void
    {
        $rid = (int)($this->edit['room_id'] ?? 0);
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        $this->dayPickStatuses = [];
        if ($rid<=0 || $fid<=0) return;

        $offeringId = (int)($this->editingOfferingId ?? 0);
        $cid = (int)($this->editingCid ?? 0);

        if ($mode === 'SINGLE') {
            foreach (['MON','TUE','WED','THU','FRI','SAT'] as $day) {
                $day = $this->normalizeDay($day);
                $wins = $this->facultyAvailWindows($fid, $day);

                $ok = false;
                foreach ($this->singleBlocks as $b) {
                    [$st,$et] = $b;

                    if (!$this->coversAnyWindow($wins, $st, $et)) continue;

                    $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, $offeringId, $cid);
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
            $p1 = $this->normalizeDay($pair[0]);
            $p2 = $this->normalizeDay($pair[1]);

            $key = "{$p1}/{$p2}";
            $ok = false;

            foreach ($this->pairBlocks as $b) {
                [$st,$et] = $b;

                if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) continue;

                $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, $offeringId, $cid);
                $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, $offeringId, $cid);

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
            $this->edit['days'] = [$this->normalizeDay($parts[0]), $this->normalizeDay($parts[1])];
        } else {
            $this->edit['days'] = [$this->normalizeDay($key)];
        }

        $this->edit['time_slot_id'] = null;
        $this->edit['start_time'] = null;
        $this->edit['end_time'] = null;

        $this->computeTimesForSelectedDay();

        $this->pickStep = 'TIME';
        $this->editWarning = null;
    }

    private function computeTimesForSelectedDay(): void
    {
        $rid  = (int)($this->edit['room_id'] ?? 0);
        $fid  = (int)($this->edit['faculty_id'] ?? 0);
        $days = $this->edit['days'] ?? [];
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));

        $this->timePickStatuses = [];
        if ($rid<=0 || $fid<=0 || empty($days)) return;

        $offeringId = (int)($this->editingOfferingId ?? 0);
        $cid = (int)($this->editingCid ?? 0);

        if ($mode === 'SINGLE') {
            $day = $this->normalizeDay((string)($days[0] ?? 'MON'));
            $wins = $this->facultyAvailWindows($fid, $day);

            foreach ($this->singleBlocks as $i => $b) {
                [$st,$et] = $b;

                if (!$this->coversAnyWindow($wins, $st, $et)) {
                    $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Not covered by faculty availability'];
                    continue;
                }

                $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, $offeringId, $cid);
                if ($conf['faculty']) { $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> "Faculty conflict"]; continue; }
                if ($conf['room'])    { $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> "Room conflict"]; continue; }

                $this->timePickStatuses[$i] = ['ok'=>true,'label'=> "{$st}–{$et}",'reason'=> null];
            }
            return;
        }

        $p1 = $this->normalizeDay((string)($days[0] ?? 'MON'));
        $p2 = $this->normalizeDay((string)($days[1] ?? 'WED'));

        foreach ($this->pairBlocks as $i => $b) {
            [$st,$et] = $b;

            if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) {
                $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Not covered by availability for BOTH days'];
                continue;
            }

            $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, $offeringId, $cid);
            $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, $offeringId, $cid);

            if ($c1['faculty'] || $c2['faculty']) { $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Faculty conflict on one of the pair days']; continue; }
            if ($c1['room'] || $c2['room'])       { $this->timePickStatuses[$i] = ['ok'=>false,'label'=> "{$st}–{$et}",'reason'=> 'Room conflict on one of the pair days']; continue; }

            $this->timePickStatuses[$i] = ['ok'=>true,'label'=> "{$st}–{$et}",'reason'=> null];
        }
    }

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

        $this->edit['time_slot_id'] = $idx;
        $this->edit['start_time'] = $st;
        $this->edit['end_time']   = $et;
        $this->editWarning = null;
    }

    public function saveManual(): void
    {
        $offeringId = (int)($this->editingOfferingId ?? 0);
        $cid = (int)($this->editingCid ?? 0);
        $fid = (int)($this->edit['faculty_id'] ?? 0);
        $rid = (int)($this->edit['room_id'] ?? 0);
        $sel = $this->edit['time_slot_id'];

        if ($offeringId<=0 || $cid<=0 || $fid<=0 || $rid<=0 || $sel === null) {
            $this->editWarning = 'Please pick Faculty → Room → Day → Time (all green).';
            return;
        }

        $sel = (int)$sel;
        if (!($this->timePickStatuses[$sel]['ok'] ?? false)) {
            $this->editWarning = $this->timePickStatuses[$sel]['reason'] ?? 'Selected time is not valid.';
            return;
        }

        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));
        $days = $this->edit['days'] ?? ($mode === 'SINGLE' ? ['MON'] : ['MON','WED']);
        $days = array_values(array_filter(array_map(fn($d) => $this->normalizeDay((string)$d), $days)));

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

        // duration check
        $mins = $this->slotMinutes($st, $et);
        $required = ($mode === 'SINGLE') ? 180 : 90;
        if ($mins !== $required) {
            $this->editWarning = "Invalid duration: selected slot is {$mins} mins. Required {$required} mins for {$mode}.";
            return;
        }

        // HARD RECHECK conflicts
        foreach ($days as $d) {
            $conf = $this->checkConflictRoomFaculty($rid, $fid, $d, $st, $et, $offeringId, $cid);
            if (!empty($conf['faculty'])) { $this->editWarning = "Cannot save: Faculty conflict on {$d} {$st}-{$et}."; return; }
            if (!empty($conf['room']))    { $this->editWarning = "Cannot save: Room conflict on {$d} {$st}-{$et}."; return; }
        }

        DB::transaction(function () use ($offeringId, $cid, $fid, $rid, $days, $st, $et) {
            SectionMeeting::where('offering_id', $offeringId)
                ->where('curriculum_id', $cid)
                ->delete();

            $rows = [];
            foreach ($days as $d) {
                $rows[] = [
                    'offering_id'   => $offeringId,
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

        session()->flash('success', 'Manual schedule saved successfully.');
        $this->loadAllSections();
        $this->closeManual();
        $this->dispatch('$refresh');
    }

    /* ===================== FEASIBILITY HELPERS ===================== */

    private function existsAnyValidDayTime(int $rid, int $fid): bool
    {
        $mode = strtoupper((string)($this->edit['day_mode'] ?? 'PAIR'));
        $offeringId = (int)($this->editingOfferingId ?? 0);
        $cid = (int)($this->editingCid ?? 0);

        if ($mode === 'SINGLE') {
            foreach (['MON','TUE','WED','THU','FRI','SAT'] as $day) {
                $day = $this->normalizeDay($day);
                $wins = $this->facultyAvailWindows($fid, $day);
                if (empty($wins)) continue;

                foreach ($this->singleBlocks as $b) {
                    [$st,$et] = $b;

                    if (!$this->coversAnyWindow($wins, $st, $et)) continue;

                    $conf = $this->checkConflictRoomFaculty($rid, $fid, $day, $st, $et, $offeringId, $cid);
                    if (!$conf['room'] && !$conf['faculty']) return true;
                }
            }
            return false;
        }

        foreach ([['MON','WED'], ['TUE','THU']] as $pair) {
            $p1 = $this->normalizeDay($pair[0]);
            $p2 = $this->normalizeDay($pair[1]);

            foreach ($this->pairBlocks as $b) {
                [$st,$et] = $b;

                if (!$this->coversPairWindows($fid, [$p1,$p2], $st, $et)) continue;

                $c1 = $this->checkConflictRoomFaculty($rid, $fid, $p1, $st, $et, $offeringId, $cid);
                $c2 = $this->checkConflictRoomFaculty($rid, $fid, $p2, $st, $et, $offeringId, $cid);

                if (!$c1['room'] && !$c1['faculty'] && !$c2['room'] && !$c2['faculty']) return true;
            }
        }

        return false;
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

    /* ===================== MISC HELPERS ===================== */

    private function recomputeGenerateState(): void
    {
        $hasEmpty = false;

        foreach ($this->allSections as $sec) {
            foreach (($sec['plan'] ?? []) as $row) {
                if (empty($row['faculty_id']) || empty($row['room_id']) || empty($row['day']) || empty($row['start_time']) || empty($row['end_time'])) {
                    $hasEmpty = true;
                    break 2;
                }
            }
        }

        $this->isComplete = !$hasEmpty;
        $this->canGenerate = $hasEmpty;
    }

    private function meetingIsComplete(array $rows): bool
    {
        if (empty($rows)) return false;

        foreach ($rows as $m) {
            if (empty($m->faculty_id) || empty($m->room_id) || empty($m->day) || empty($m->start_time) || empty($m->end_time)) {
                return false;
            }
        }
        return true;
    }

    /* ===================== RENDER ===================== */

    public function render()
    {
        // still okay as lookup maps for name/code in your blade
        $faculty = User::orderBy('name')->get(['id','name']);
        $rooms   = Room::orderBy('code')->get(['id','code']);

        return view('livewire.head.schedulings.editor', compact('faculty','rooms'));
    }
}
