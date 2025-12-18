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

    public array $incMap = []; // ✅ curriculum_id => reason
    private array $specNames = [];

    public function mount(CourseOffering $offering)
    {
        $this->offering = $offering;
        $this->alreadyGenerated = SectionMeeting::where('offering_id', $offering->id)->exists();

        $this->loadPlan();
        $this->hydratePlanFromMeetings();
    }

    public function loadPlan(): void
    {
        $this->specNames = class_exists(Specialization::class)
            ? Specialization::pluck('name', 'id')->all()
            : [];

        $yearCol     = $this->curriculaYearColumn();
        $yearAliases = $this->yearAliases($this->offering->year_level);
        $semAliases  = $this->semesterAliases(optional($this->offering->academic)->semester);

        $q = Curriculum::query();

        if (Schema::hasColumn('curricula', 'course_id') && !empty($this->offering->course_id)) {
            $q->where('course_id', $this->offering->course_id);
        }

        if ($yearCol && !empty($yearAliases)) {
            $q->where(function ($qq) use ($yearCol, $yearAliases) {
                foreach ($yearAliases as $a) {
                    $qq->orWhere($yearCol, 'like', $a)->orWhere($yearCol, 'like', "%{$a}%");
                }
            });
        }

        if (Schema::hasColumn('curricula', 'semester') && !empty($semAliases)) {
            $q->where(function ($qq) use ($semAliases) {
                foreach ($semAliases as $a) {
                    $qq->orWhere('semester', 'like', $a)->orWhere('semester', 'like', "%{$a}%");
                }
            });
        }

        $subjects = $q->orderBy('course_code')->get();

        if ($subjects->isEmpty()) {
            $ids = SectionMeeting::where('offering_id', $this->offering->id)
                ->pluck('curriculum_id')->unique()->filter()->values();

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

                // UI helper
                'inc'               => false,
                'inc_reason'        => null,
            ];
        })->values()->all();

        $this->planLoaded = true;
        $this->alreadyGenerated = SectionMeeting::where('offering_id', $this->offering->id)->exists();
    }

    public function generate(): void
    {
        @set_time_limit(300);
        session()->forget(['success','offerings_warning','ga_debug']);

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

            // ✅ important
            'ENFORCE_PT_AVAIL_STRICT'    => true,
        ]);

        $result = $ga->run();
        // ✅ save INC map for UI
        $this->incMap = $result['inc'] ?? [];

        if (empty($result['meetings'])) {
            session()->flash('offerings_warning', 'No meetings generated. Subjects may be INC (no eligible faculty / PT availability / max units / conflicts). Check GA Debug.');
            $this->hydratePlanFromMeetings(); // will also apply INC badges
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

    /**
     * ✅ Merge meetings into plan rows + set INC badge if not scheduled.
     */
    private function hydratePlanFromMeetings(): void
    {
        if (empty($this->plan)) return;

        $index = [];
        foreach ($this->plan as $i => $row) {
            $index[(int)($row['curriculum_id'] ?? 0)] = $i;
        }

        // reset schedule fields
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
            $this->plan[$idx]['start_time'] = $m?->start_time;
            $this->plan[$idx]['end_time']   = $m?->end_time;
        }

        // ✅ apply INC marks (based on last GA run)
        foreach ($this->incMap as $cid => $reason) {
            $cid = (int)$cid;
            if (!isset($index[$cid])) continue;

            $idx = $index[$cid];

            // only mark INC if still no meeting
            if (empty($this->plan[$idx]['day'])) {
                $this->plan[$idx]['inc'] = true;
                $this->plan[$idx]['inc_reason'] = (string)$reason;
            }
        }
    }

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

    public function render()
    {
        $faculty = User::orderBy('name')->get(['id','name']);
        $rooms   = Room::orderBy('code')->get(['id','code']);

        $totalUnits = 0;
        foreach (($this->plan ?? []) as $r) $totalUnits += (int)($r['units'] ?? 0);

        return view('livewire.head.schedulings.editor', compact('faculty','rooms','totalUnits'));
    }
}
