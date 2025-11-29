<?php

namespace App\Livewire\Dean;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\SectionMeeting;
use App\Models\AcademicYear;

#[Title('Department Faculty Loads')]
#[Layout('layouts.dean-shell')]
class Depworkloads extends Component
{
    public $academicId;
    public bool $showHistory = false;

    /** @var int|null selected course (belongs to Dean's department) */
    public ?int $courseId = null;

    /** @var int|null selected faculty (user_id) filter */
    public ?int $facultyId = null;

    /** @var array<int, array{value:int,label:string}> */
    public array $courseOptions = [];

    /** @var array<int, array{value:int,label:string}> */
    public array $facultyOptions = [];

    /** @var array<int, array<string,mixed>> */
    public array $sheets = [];

    /** @var array<int> */
    private array $myDepartmentIds = [];

    public function mount(): void
    {
        $this->showHistory = (bool) (request()->query('history') ?? false);
        $this->academicId  = request()->query('academic') ?: null;
        $this->courseId    = (int) (request()->query('course') ?? 0) ?: null;
        $this->facultyId   = (int) (request()->query('faculty') ?? 0) ?: null;

        // Default to active term (or latest past if History view)
        if (is_null($this->academicId)) {
            $activeId = AcademicYear::where('is_active', 1)->value('id');
            if ($this->showHistory) {
                $latestPast = AcademicYear::when($activeId, fn($q) => $q->where('id', '!=', $activeId))
                    ->orderByDesc('id')->value('id');
                $this->academicId = $latestPast ?: $activeId;
            } else {
                $this->academicId = $activeId;
            }
        }

        $this->myDepartmentIds = $this->detectMyDepartmentIds();
        $this->buildCourseOptions();
        $this->buildSheets();
    }

    #[On('term-changed')]
    public function onTermChanged(int $activeAcademicId, ?int $previousAcademicId = null): void
    {
        if ($previousAcademicId) {
            $this->redirect(
                url()->current() . '?history=1&academic=' . $previousAcademicId,
                navigate: true
            );
            return;
        }

        $this->redirect(
            url()->current() . '?history=0&academic=' . $activeAcademicId,
            navigate: true
        );
    }

    /**
     * Department IDs for the logged-in Dean.
     */
    private function detectMyDepartmentIds(): array
    {
        $ids = [];
        $me  = Auth::user();
        if (!$me) return [];

        // users.department_id
        if (Schema::hasColumn('users', 'department_id')) {
            $did = (int) ($me->department_id ?? 0);
            if ($did > 0) $ids[] = $did;
        }

        // users_departments pivot
        if (Schema::hasTable('users_departments')) {
            $cols = Schema::getColumnListing('users_departments');
            if (in_array('user_id', $cols, true) && in_array('department_id', $cols, true)) {
                $extra = DB::table('users_departments')
                    ->where('user_id', (int) $me->id)
                    ->pluck('department_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $ids = array_merge($ids, array_map('intval', $extra));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Build course options for the Dean's department.
     */
    private function buildCourseOptions(): void
    {
        $this->courseOptions = [];

        if (empty($this->myDepartmentIds) || !Schema::hasTable('courses')) {
            return;
        }

        $cols = Schema::getColumnListing('courses');

        $labelCol = null;
        foreach ([
            'abbr','code','course_code','short_code','shortname','short_name',
            'name','title','program','program_name','course','course_name'
        ] as $cand) {
            if (in_array($cand, $cols, true)) { $labelCol = $cand; break; }
        }
        if (!$labelCol) {
            $labelCol = 'id';
        }

        $q = DB::table('courses')
            ->when(in_array('department_id', $cols, true) && !empty($this->myDepartmentIds),
                fn($q) => $q->whereIn('department_id', $this->myDepartmentIds))
            ->orderBy($labelCol);

        $this->courseOptions = collect($q->get())
            ->map(fn($r) => [
                'value' => (int) $r->id,
                'label' => (string) $r->{$labelCol},
            ])
            ->values()
            ->all();
    }

    /**
     * Build sheets:
     * - one page per faculty (user) under Dean's department
     * - optional: filter by course + faculty
     * - excludes Dean's own loads
     */
    private function buildSheets(): void
    {
        // reset current state
        $this->sheets         = [];
        $this->facultyOptions = [];

        $me = Auth::user();
        if (!$me) return;

        if (
            !Schema::hasTable('section_meetings') ||
            !Schema::hasTable('course_offerings')
        ) {
            return;
        }

        $deanId = (int) $me->id;

        // optional joins for labels
        $joinCourses = Schema::hasTable('courses'); $courseCol = null;
        if ($joinCourses) {
            foreach ([
                'abbr','code','course_code','short_code','shortname','short_name',
                'name','title','program','program_name','course','course_name'
            ] as $cand) {
                if (Schema::hasColumn('courses', $cand)) { $courseCol = $cand; break; }
            }
            if (!$courseCol) $joinCourses = false;
        }

        $joinSections = Schema::hasTable('sections'); $sectionCol = null;
        if ($joinSections) {
            foreach (['name','section','code','label','title','section_name'] as $cand) {
                if (Schema::hasColumn('sections', $cand)) { $sectionCol = $cand; break; }
            }
            if (!$sectionCol) $joinSections = false;
        }

        // ===== Base query (meeting-level) =====
        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->when($joinCourses, fn($q) => $q->leftJoin('courses as c','c.id','=','co.course_id'))
            ->when($joinSections, fn($q) => $q->leftJoin('sections as s','s.id','=','co.section_id'))
            ->when(
                !is_null($this->academicId) && Schema::hasColumn('course_offerings','academic_id'),
                fn($q) => $q->where('co.academic_id', $this->academicId)
            )
            // 🔹 Filter by selected course (kung napili na ang course)
            ->when($this->courseId, fn($q) => $q->where('co.course_id', $this->courseId))
            ->with(['curriculum','room','offering.academic']);

        // ===== Map meetings -> users (all faculty/heads) =====
        $hasSMInstrUID = Schema::hasColumn('section_meetings', 'instructor_user_id');
        $hasSMFacId    = Schema::hasColumn('section_meetings', 'faculty_id');
        $hasFacTable   = Schema::hasTable('faculties');
        $hasFacUserId  = $hasFacTable && Schema::hasColumn('faculties', 'user_id');

        if ($hasSMInstrUID) {
            $q->join('users as u', 'u.id', '=', 'section_meetings.instructor_user_id');
        } elseif ($hasSMFacId && $hasFacUserId) {
            $q->leftJoin('faculties as f', 'f.id', '=', 'section_meetings.faculty_id')
              ->join('users as u', 'u.id', '=', 'f.user_id');
        } elseif ($hasSMFacId) {
            $q->join('users as u', 'u.id', '=', 'section_meetings.faculty_id');
        } else {
            // cannot map to users -> nothing meaningful to show
            return;
        }

        // Restrict to Dean's department (if detected)
        if (!empty($this->myDepartmentIds)) {
            if (Schema::hasColumn('users', 'department_id')) {
                $q->whereIn('u.department_id', $this->myDepartmentIds);
            } elseif (Schema::hasTable('users_departments')) {
                $cols = Schema::getColumnListing('users_departments');
                if (in_array('user_id', $cols, true) && in_array('department_id', $cols, true)) {
                    $q->leftJoin('users_departments as ud', 'ud.user_id', '=', 'u.id')
                      ->whereIn('ud.department_id', $this->myDepartmentIds);
                }
            }
        }

        // Exclude the Dean's own loads
        $q->where('u.id', '!=', $deanId);

        $q->select([
            'section_meetings.*',
            'co.id as offering_id',
            'co.course_id',
            'co.academic_id as academic_id',
            DB::raw($joinCourses ? "c.`{$courseCol}` as course_label" : "NULL as course_label"),
            DB::raw($joinSections ? "s.`{$sectionCol}` as section_label" : "co.section_id as section_label"),
            'u.id as faculty_user_id',
            'u.name as faculty_name',
        ])
        ->orderBy('u.name')
        ->orderByRaw("FIELD(day,'MON','TUE','WED','THU','FRI','SAT','SUN')")
        ->orderBy('start_time');

        $rows = $q->get();
        if ($rows->isEmpty()) {
            return;
        }

        // ===== Group: 1 sheet per faculty =====
        $byFaculty  = [];

        foreach ($rows as $m) {
            $uid   = (int) ($m->faculty_user_id ?? 0);
            $fname = (string) ($m->faculty_name ?? '');
            if ($uid <= 0 || $fname === '') continue;

            if (!isset($byFaculty[$uid]['meta'])) {
                $byFaculty[$uid]['meta'] = [
                    'faculty'      => $this->shortName($fname),
                    'faculty_full' => $fname,
                    'school_year'  => $this->schoolYear($m),
                    'semester'     => $this->semesterLabel($m),
                ];
            }

            $offId  = (int) $m->offering_id;
            $cid    = (int) $m->curriculum_id;
            $secLbl = (string) ($m->section_label ?? '');

            $byFaculty[$uid]['bucket'][$offId]['section'] ??= $secLbl;

            if (!isset($byFaculty[$uid]['bucket'][$offId]['curr'][$cid])) {
                $byFaculty[$uid]['bucket'][$offId]['curr'][$cid] = [
                    'code'  => $m->curriculum?->course_code,
                    'title' => $m->curriculum?->descriptive_title,
                    'units' => (int) ($m->curriculum?->units ?? 0),
                    'meets' => [],
                ];
            }

            $byFaculty[$uid]['bucket'][$offId]['curr'][$cid]['meets'][] = [
                'day'  => $m->day,
                'st'   => $m->start_time,
                'et'   => $m->end_time,
                'room' => $m->room?->code,
                'prof' => $fname,
            ];
        }

        $allSheets = [];
        foreach ($byFaculty as $uid => $pack) {
            $meta   = $pack['meta']   ?? [];
            $bucket = $pack['bucket'] ?? [];

            $sheet = [
                'user_id'       => (int) $uid,
                'faculty'       => $meta['faculty_full'] ?? '',
                'faculty_short' => $meta['faculty']      ?? '',
                'school_year'   => $meta['school_year']  ?? '',
                'semester'      => $meta['semester']     ?? '',
                'rows'          => [],
            ];

            foreach ($bucket as $offPack) {
                $sectionName = (string) ($offPack['section'] ?? '');
                $currs       = $offPack['curr'] ?? [];

                foreach ($currs as $c) {
                    [$st, $et, $days, $room, $prof] = $this->reduceMeetings($c['meets'] ?? []);
                    $sheet['rows'][] = [
                        'code'    => $c['code'],
                        'title'   => $c['title'],
                        'units'   => $c['units'],
                        'section' => $sectionName,
                        'st'      => $st,
                        'et'      => $et,
                        'days'    => $days,
                        'room'    => $room,
                        'inst'    => $prof,
                    ];
                }
            }

            usort($sheet['rows'], function ($a, $b) {
                $s = strcmp((string) $a['section'], (string) $b['section']);
                return $s !== 0 ? $s : strcmp((string) $a['code'], (string) $b['code']);
            });

            $allSheets[] = $sheet;
        }

        // sort sheets by faculty name
        usort($allSheets, fn($a, $b) => strcmp((string) $a['faculty'], (string) $b['faculty']));

        // 🔹 Build facultyOptions: depende sa COURSE
        $this->facultyOptions = [];
        if ($this->courseId) {
            $map = [];
            foreach ($allSheets as $sheet) {
                $uid   = (int) ($sheet['user_id'] ?? 0);
                $label = (string) ($sheet['faculty_short'] ?: $sheet['faculty'] ?: '');
                if ($uid > 0 && $label !== '') {
                    $map[$uid] = $label;
                }
            }

            asort($map, SORT_NATURAL | SORT_FLAG_CASE);

            $opts = [];
            foreach ($map as $id => $label) {
                $opts[] = [
                    'value' => (int) $id,
                    'label' => (string) $label,
                ];
            }
            $this->facultyOptions = $opts;
        }

        // 🔹 Apply faculty filter to sheets (after building options)
        if ($this->facultyId) {
            $allSheets = array_values(
                array_filter($allSheets, fn($s) => (int) ($s['user_id'] ?? 0) === (int) $this->facultyId)
            );
        }

        $this->sheets = $allSheets;
    }

    private function schoolYear($m): string
    {
        return (string) ($m?->offering?->academic?->school_year ?? '');
    }

    private function semesterLabel($m): string
    {
        $raw = (string) ($m?->offering?->academic?->semester ?? '');
        $map = [
            '1'      => '1st',
            '1ST'    => '1st',
            'FIRST'  => '1st',
            '2'      => '2nd',
            '2ND'    => '2nd',
            'SECOND' => '2nd',
            '3'      => 'Midyear',
            'MIDYEAR'=> 'Midyear',
            'SUMMER' => 'Midyear',
        ];
        $up = strtoupper(trim($raw));
        return $map[$up] ?? $raw;
    }

    private function reduceMeetings(array $meets): array
    {
        if (empty($meets)) return ['','','','',''];
        $bySig = [];
        foreach ($meets as $m) {
            $sig = implode('|', [
                (string)($m['st'] ?? ''),(string)($m['et'] ?? ''),(string)($m['room'] ?? ''),(string)($m['prof'] ?? '')
            ]);
            $bySig[$sig]['days'][] = (string)($m['day'] ?? '');
            $bySig[$sig]['st']     = (string)($m['st'] ?? '');
            $bySig[$sig]['et']     = (string)($m['et'] ?? '');
            $bySig[$sig]['room']   = (string)($m['room'] ?? '');
            $bySig[$sig]['prof']   = $this->shortName((string)($m['prof'] ?? ''));
        }
        $first = reset($bySig) ?: [];
        $days  = $this->mergeDays((array)($first['days'] ?? []));
        return [
            $this->to12h((string)($first['st'] ?? '')),
            $this->to12h((string)($first['et'] ?? '')),
            $days,
            (string)($first['room'] ?? ''),
            (string)($first['prof'] ?? ''),
        ];
    }

    private function mergeDays(array $days): string
    {
        $set = array_values(array_filter(array_unique($days)));
        sort($set);
        $has = array_flip($set);
        $out = [];
        $abbr = fn($d) => [
            'MON'=>'M','TUE'=>'T','WED'=>'W','THU'=>'TH','FRI'=>'F','SAT'=>'S','SUN'=>'SU'
        ][$d] ?? $d;

        foreach ([['MON','WED'], ['TUE','THU']] as [$a, $b]) {
            if (isset($has[$a]) && isset($has[$b])) {
                $out[] = $abbr($a) . '/' . $abbr($b);
                unset($has[$a], $has[$b]);
            }
        }
        foreach (array_keys($has) as $d) {
            $out[] = $abbr($d);
        }
        return implode(' ', $out);
    }

    private function to12h(?string $hhmm): string
    {
        if (!$hhmm) return '';
        $t = substr($hhmm, 0, 5);
        if (strpos($t, ':') === false) return $hhmm;
        [$h, $m] = explode(':', $t);
        $h    = (int) $h;
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $h    = $h % 12;
        if ($h === 0) $h = 12;
        return sprintf('%02d:%02d %s', $h, (int) $m, $ampm);
    }

    private function shortName(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $parts = preg_split('/\s+/', $name);
        $last  = strtoupper(array_pop($parts));
        $first = $parts ? strtoupper(substr($parts[0], 0, 1)) . '.' : '';
        return trim($first . ' ' . $last);
    }

    public function render()
    {
        $terms       = AcademicYear::orderByDesc('id')->get();
        $activeId    = AcademicYear::where('is_active', 1)->value('id');
        $currentTerm = $terms->firstWhere('id', (int) ($this->academicId ?? 0));

        return view('livewire.dean.depworkloads', [
            'sheets'         => $this->sheets,
            'academicId'     => $this->academicId,
            'showHistory'    => $this->showHistory,
            'terms'          => $terms,
            'activeId'       => $activeId,
            'currentTerm'    => $currentTerm,
            'courseOptions'  => $this->courseOptions,
            'courseId'       => $this->courseId,
            'facultyOptions' => $this->facultyOptions,
            'facultyId'      => $this->facultyId,
        ]);
    }
}
