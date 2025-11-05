<?php

namespace App\Livewire\Head;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\SectionMeeting;
use App\Models\AcademicYear;

#[Title('Dashboard')]
#[Layout('layouts.head-shell')]
class Dashboard extends Component
{
    public $academicId;                // term to view (active or old)
    public ?int $sectionId = null;
    /** @var array<int, array{value:int,label:string}> */
    public array $sectionOptions = [];
    public array $sheets = [];
    public bool $showHistory = false;  // NEW: history mode toggle

    private array $myCourseIds = [];

    public function mount(): void
    {
        $this->showHistory = (bool) (request()->query('history') ?? false);
        $this->academicId  = request()->query('academic') ?: null;
        $this->sectionId   = (int) (request()->query('section') ?? 0) ?: null;

        // Defaults:
        // - Not history ⇒ default to ACTIVE term
        // - History and no academicId ⇒ default to latest NON-active term
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

        $this->myCourseIds = $this->detectMyCourseIds();
        $this->buildSectionOptions();
        $this->buildSheets();
    }

    #[On('term-changed')]
    public function onTermChanged(int $activeAcademicId, ?int $previousAcademicId = null): void
    {
        // Redirect to Previous term in History mode so makita dayon
        if ($previousAcademicId) {
            $this->redirect(
                url()->current() . '?history=1&academic=' . $previousAcademicId,
                navigate: true
            );
            return;
        }

        // Fallback: go to (likely empty) new active term
        $this->redirect(
            url()->current() . '?history=0&academic=' . $activeAcademicId,
            navigate: true
        );
    }

    private function detectMyCourseIds(): array
    {
        $ids = [];
        $me = Auth::user();
        if (!$me) return [];

        if (Schema::hasColumn('users', 'course_id')) {
            $cid = (int)($me->course_id ?? 0);
            if ($cid > 0) $ids[] = $cid;
        }

        if (Schema::hasTable('course_offerings')
            && Schema::hasColumn('course_offerings', 'head_user_id')
            && Schema::hasColumn('course_offerings', 'course_id')) {

            $owned = DB::table('course_offerings')
                ->where('head_user_id', (int)$me->id)
                ->pluck('course_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $ids = array_values(array_unique(array_merge($ids, array_map('intval', $owned))));
        }

        return $ids;
    }

    private function buildSectionOptions(): void
    {
        $this->sectionOptions = [];
        if (empty($this->myCourseIds) || !Schema::hasTable('course_offerings')) return;

        $joinSections = Schema::hasTable('sections');
        $sectionCol = null;
        if ($joinSections) {
            foreach (['name','section','code','label','title','section_name'] as $cand) {
                if (Schema::hasColumn('sections', $cand)) { $sectionCol = $cand; break; }
            }
            if (!$sectionCol) $joinSections = false;
        }

        $q = DB::table('course_offerings as co')
            ->when($joinSections, fn($q) => $q->leftJoin('sections as s', 's.id', '=', 'co.section_id'))
            ->whereIn('co.course_id', $this->myCourseIds)
            ->when(!is_null($this->academicId) && Schema::hasColumn('course_offerings','academic_id'),
                fn($q) => $q->where('co.academic_id', $this->academicId))
            ->whereNotNull('co.section_id')
            ->select([
                'co.section_id',
                DB::raw($joinSections ? "s.`{$sectionCol}` as label" : "co.section_id as label"),
            ])
            ->distinct()
            ->orderBy('label');

        $this->sectionOptions = collect($q->get())
            ->map(fn($r) => ['value' => (int)$r->section_id, 'label' => (string)$r->label])
            ->values()
            ->all();
    }

    private function buildSheets(): void
    {
        $this->sheets = [];
        if (empty($this->myCourseIds)) return;

        $joinSections = false; $sectionCol = null;
        if (Schema::hasTable('sections')) {
            foreach (['name','section','code','label','title','section_name'] as $cand) {
                if (Schema::hasColumn('sections', $cand)) { $sectionCol = $cand; break; }
            }
            if ($sectionCol) $joinSections = true;
        }

        $joinCourses = Schema::hasTable('courses'); $courseCol = null;
        if ($joinCourses) {
            foreach (['abbr','code','course_code','short_code','shortname','short_name','name','title','program','program_name','course','course_name'] as $cand) {
                if (Schema::hasColumn('courses', $cand)) { $courseCol = $cand; break; }
            }
            if (!$courseCol) $joinCourses = false;
        }

        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->when($joinSections, fn($q) => $q->leftJoin('sections as s','s.id','=','co.section_id'))
            ->when($joinCourses, fn($q) => $q->leftJoin('courses as c','c.id','=','co.course_id'))
            ->whereIn('co.course_id', $this->myCourseIds)
            ->when($this->sectionId, fn($q) => $q->where('co.section_id', $this->sectionId))
            ->when(!is_null($this->academicId) && Schema::hasColumn('course_offerings','academic_id'),
                fn($q) => $q->where('co.academic_id', $this->academicId))
            ->select([
                'section_meetings.*',
                'co.id as offering_id',
                'co.course_id',
                DB::raw($joinSections ? "s.`{$sectionCol}` as section_label" : "co.section_id as section_label"),
                DB::raw($joinCourses ? "c.`{$courseCol}` as course_label" : "NULL as course_label"),
                'co.year_level as co_year_level',
                'co.academic_id as academic_id',
            ])
            ->with(['curriculum','room','faculty','offering.academic'])
            ->orderByRaw("FIELD(day,'MON','TUE','WED','THU','FRI','SAT')")
            ->orderBy('start_time');

        $rows = $q->get();

        $byOffering = [];
        foreach ($rows as $m) {
            $offId = (int)$m->offering_id;
            $courseLabel = $m->course_label ? strtoupper((string)$m->course_label) : 'COURSE';

            $byOffering[$offId]['meta'] ??= [
                'course'      => $courseLabel,
                'school_year' => $this->schoolYear($m),
                'semester'    => $this->semesterLabel($m),
                'year_level'  => $this->yearLevelLabel($m),
                'section'     => (string)($m->section_label ?? ''),
            ];

            $cid = (int)$m->curriculum_id;
            $byOffering[$offId]['curr'][$cid] ??= [
                'code'  => $m->curriculum?->course_code,
                'title' => $m->curriculum?->descriptive_title,
                'units' => (int)($m->curriculum?->units ?? 0),
                'meets' => [],
            ];

            $byOffering[$offId]['curr'][$cid]['meets'][] = [
                'day'  => $m->day,
                'st'   => $m->start_time,
                'et'   => $m->end_time,
                'room' => $m->room?->code,
                'prof' => $m->faculty?->name,
                'inc'  => ($m->notes === 'INC'),
            ];
        }

        foreach ($byOffering as $pack) {
            $sheet = [
                'course'      => $pack['meta']['course'] ?? 'COURSE',
                'school_year' => $pack['meta']['school_year'] ?? '',
                'semester'    => $pack['meta']['semester'] ?? '',
                'year_level'  => $pack['meta']['year_level'] ?? '',
                'section'     => $pack['meta']['section'] ?? '',
                'rows'        => [],
            ];

            foreach ($pack['curr'] as $c) {
                [$st,$et,$days,$room,$prof] = $this->reduceMeetings($c['meets']);
                $sheet['rows'][] = [
                    'code'  => $c['code'],
                    'title' => $c['title'],
                    'units' => $c['units'],
                    'st'    => $st,
                    'et'    => $et,
                    'days'  => $days,
                    'room'  => $room,
                    'inst'  => $prof,
                ];
            }

            usort($sheet['rows'], fn($a,$b) => strcmp((string)$a['code'], (string)$b['code']));
            $this->sheets[] = $sheet;
        }

        usort($this->sheets, fn($a,$b) => strcmp((string)$a['section'], (string)$b['section']));
    }

    private function schoolYear($m): string
    { return (string)($m?->offering?->academic?->school_year ?? ''); }

    private function semesterLabel($m): string
    {
        $raw = (string)($m?->offering?->academic?->semester ?? '');
        $map = ['1'=>'1st','FIRST'=>'1st','2'=>'2nd','SECOND'=>'2nd','3'=>'Midyear','MIDYEAR'=>'Midyear','SUMMER'=>'Midyear'];
        $up = strtoupper(trim($raw));
        return $map[$up] ?? $raw;
    }

    private function yearLevelLabel($m): string
    {
        $yl = (string)($m?->co_year_level ?? '');
        $map = ['1'=>'First','FIRST YEAR'=>'First','2'=>'Second','SECOND YEAR'=>'Second','3'=>'Third','THIRD YEAR'=>'Third','4'=>'Fourth','FOURTH YEAR'=>'Fourth'];
        $up = strtoupper(trim($yl));
        return $map[$up] ?? $yl;
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
        $abbr = fn($d) => ['MON'=>'M','TUE'=>'T','WED'=>'W','THU'=>'TH','FRI'=>'F','SAT'=>'S','SUN'=>'SU'][$d] ?? $d;

        foreach ([['MON','WED'], ['TUE','THU']] as [$a,$b]) {
            if (isset($has[$a]) && isset($has[$b])) {
                $out[] = $abbr($a).'/'.$abbr($b);
                unset($has[$a], $has[$b]);
            }
        }
        foreach (array_keys($has) as $d) $out[] = $abbr($d);
        return implode(' ', $out);
    }

    private function to12h(?string $hhmm): string
    {
        if (!$hhmm) return '';
        $t = substr($hhmm, 0, 5);
        if (strpos($t, ':') === false) return $hhmm;
        [$h,$m] = explode(':', $t);
        $h = (int)$h; $ampm = $h >= 12 ? 'PM' : 'AM';
        $h = $h % 12; if ($h === 0) $h = 12;
        return sprintf('%02d:%02d %s', $h, (int)$m, $ampm);
    }

    private function shortName(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '';
        $parts = preg_split('/\s+/', $name);
        $last  = strtoupper(array_pop($parts));
        $first = $parts ? strtoupper(substr($parts[0],0,1)).'.' : '';
        return trim($first.' '.$last);
    }

    public function render()
    {
        return view('livewire.head.dashboard', [
            'sheets'         => $this->sheets,
            'academicId'     => $this->academicId,
            'sectionOptions' => $this->sectionOptions,
            'sectionId'      => $this->sectionId,
            'showHistory'    => $this->showHistory,
        ]);
    }
}
