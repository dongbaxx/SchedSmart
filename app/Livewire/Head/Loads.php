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

#[Title('Loads')]
#[Layout('layouts.head-shell')]
class Loads extends Component
{
    public $academicId;
    public bool $showHistory = false;
    public array $sheets = [];
    public int $totalUnits = 0;

    public function mount(): void
    {
        $this->showHistory = (bool) (request()->query('history') ?? false);
        $this->academicId  = request()->query('academic') ?: null;

        // Default term selection
        if (is_null($this->academicId)) {
            $activeId = AcademicYear::where('is_active', 1)->value('id');
            if ($this->showHistory) {
                $latestPast = AcademicYear::when($activeId, fn($q) => $q->where('id','!=',$activeId))
                    ->orderByDesc('id')->value('id');
                $this->academicId = $latestPast ?: $activeId;
            } else {
                $this->academicId = $activeId;
            }
        }

        $this->buildSheets();
    }

    #[On('term-changed')]
    public function onTermChanged(int $activeAcademicId, ?int $previousAcademicId = null): void
    {
        if ($previousAcademicId) {
            $this->redirect(url()->current().'?history=1&academic='.$previousAcademicId, navigate: true);
            return;
        }
        $this->redirect(url()->current().'?history=0&academic='.$activeAcademicId, navigate: true);
    }

    /**
     * Build one printable "LOADS" sheet for the logged-in Head (their own teaching loads).
     * Same grouping/columns as Faculty dashboard, including the Section column.
     */
    private function buildSheets(): void
    {
        $this->sheets = [];
        $this->totalUnits = 0;

        $me = Auth::user();
        if (!$me) return;

        // Detect columns used to filter by the logged-in instructor
        $useCOFaculty  = Schema::hasTable('course_offerings') && Schema::hasColumn('course_offerings', 'faculty_user_id');
        $useSMFaculty  = Schema::hasTable('section_meetings') && Schema::hasColumn('section_meetings', 'faculty_id');
        $useSMInstrUID = Schema::hasTable('section_meetings') && Schema::hasColumn('section_meetings', 'instructor_user_id');

        // Optional joins for labels
        $joinCourses = Schema::hasTable('courses'); $courseCol = null;
        if ($joinCourses) {
            foreach (['abbr','code','course_code','short_code','shortname','short_name','name','title','program','program_name','course','course_name'] as $cand) {
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

        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->when($joinCourses, fn($q) => $q->leftJoin('courses as c','c.id','=','co.course_id'))
            ->when($joinSections, fn($q) => $q->leftJoin('sections as s','s.id','=','co.section_id'))
            ->when(!is_null($this->academicId) && Schema::hasColumn('course_offerings','academic_id'),
                fn($q) => $q->where('co.academic_id', $this->academicId))
            ->with(['curriculum','room','faculty','offering.academic'])
            ->select([
                'section_meetings.*',
                'co.id as offering_id',
                'co.course_id',
                'co.academic_id as academic_id',
                DB::raw($joinCourses ? "c.`{$courseCol}` as course_label" : "NULL as course_label"),
                DB::raw($joinSections ? "s.`{$sectionCol}` as section_label" : "co.section_id as section_label"),
            ])
            ->orderByRaw("FIELD(day,'MON','TUE','WED','THU','FRI','SAT','SUN')")
            ->orderBy('start_time');

        // Apply filter to only the logged-in Head's own loads
        if ($useCOFaculty) {
            $q->where('co.faculty_user_id', (int)$me->id);
        } elseif ($useSMFaculty) {
            $q->where('section_meetings.faculty_id', (int)$me->id);
        } elseif ($useSMInstrUID) {
            $q->where('section_meetings.instructor_user_id', (int)$me->id);
        } else {
            $this->sheets = [];
            return;
        }

        $rows = $q->get();

        $sheet = [
            'school_year' => $this->schoolYear($rows->first()),
            'semester'    => $this->semesterLabel($rows->first()),
            'rows'        => [],
        ];

        // Group by offering (section) + curriculum
        $bucket = []; // [$offering_id]['section'], ['curr'][$cid]
        foreach ($rows as $m) {
            $offId = (int)$m->offering_id;
            $cid   = (int)$m->curriculum_id;

            $bucket[$offId]['section'] ??= (string)($m->section_label ?? '');
            $bucket[$offId]['curr'][$cid]['code']  ??= $m->curriculum?->course_code;
            $bucket[$offId]['curr'][$cid]['title'] ??= $m->curriculum?->descriptive_title;
            $bucket[$offId]['curr'][$cid]['units'] ??= (int)($m->curriculum?->units ?? 0);
            $bucket[$offId]['curr'][$cid]['meets'] ??= [];
            $bucket[$offId]['curr'][$cid]['meets'][] = [
                'day'  => $m->day,
                'st'   => $m->start_time,
                'et'   => $m->end_time,
                'room' => $m->room?->code,
                'prof' => $m->faculty?->name,
            ];
        }

        foreach ($bucket as $offPack) {
            $sectionName = (string)($offPack['section'] ?? '');
            foreach ($offPack['curr'] as $c) {
                [$st,$et,$days,$room,$prof] = $this->reduceMeetings($c['meets'] ?? []);
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
                $this->totalUnits += (int) $c['units'];
            }
        }

        // Sort by Section then Course Code
        usort($sheet['rows'], function($a,$b){
            $s = strcmp((string)$a['section'], (string)$b['section']);
            return $s !== 0 ? $s : strcmp((string)$a['code'], (string)$b['code']);
        });

        $this->sheets[] = $sheet;
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
        return view('livewire.head.loads', [
            'sheets'      => $this->sheets,
            'academicId'  => $this->academicId,
            'showHistory' => $this->showHistory,
            'totalUnits'  => $this->totalUnits,
        ]);
    }
}
