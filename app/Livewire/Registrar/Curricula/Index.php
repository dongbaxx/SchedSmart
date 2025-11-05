<?php

namespace App\Livewire\Registrar\Curricula;

use App\Models\Curriculum;
use App\Models\Course;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Curricula')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $courseFilter = null;
    public ?int $specFilter = null;
    public string $yearFilter = '';
    public string $semFilter = '';
    public int $perPage = 10;
    public bool $dashUsesFilters = false;

    protected $queryString = [
        'search'          => ['except' => ''],
        'courseFilter'    => ['except' => null],
        'specFilter'      => ['except' => null],
        'yearFilter'      => ['except' => ''],
        'semFilter'       => ['except' => ''],
        'perPage'         => ['except' => 10],
        'dashUsesFilters' => ['except' => false],
        'page'            => ['except' => 1],
    ];

    public function updated($name, $value): void
    {
        if (in_array($name, ['search','courseFilter','specFilter','yearFilter','semFilter','perPage','page'], true)) {
            if ($name !== 'page') {
                $this->resetPage();
            }

            // Livewire v3: this already emits a browser-visible event
            $this->dispatch('scroll-to-table');
        }
    }




    protected function norm(?string $v): ?string
    {
        return is_null($v) ? null : mb_strtolower(trim($v));
    }

    public function updatingCourseFilter($newVal): void
    {
        $this->specFilter = null;
    }

    public function delete(int $id): void
    {
        $c = Curriculum::withCount('facultyLoads')->findOrFail($id);
        if ($c->faculty_loads_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete: curriculum has assigned faculty loads.');
            return;
        }
        $c->delete();
        session()->flash('success', 'Curriculum deleted.');
        $this->resetPage();
    }

    /** ─────────────────────────────────────────────── */
    /** MAIN QUERY                                      */
    /** ─────────────────────────────────────────────── */
    protected function rows()
    {
        $s = trim($this->search);

        return Curriculum::query()
            ->with(['course:id,course_name','specialization:id,name'])
            ->latest('id')
            ->when($s !== '', fn ($q) =>
                $q->where(function ($x) use ($s) {
                    $x->where('course_code', 'like', "%{$s}%")
                      ->orWhere('descriptive_title', 'like', "%{$s}%")
                      ->orWhere('pre_requisite', 'like', "%{$s}%");
                })
            )
            ->when($this->courseFilter, fn ($q) =>
                $q->where('course_id', (int) $this->courseFilter)
            )
            ->when($this->specFilter, fn ($q) =>
                $q->where('specialization_id', (int) $this->specFilter)
            )
            ->when($this->yearFilter !== '', function ($q) {
                $val = $this->norm($this->yearFilter);
                $map = [
                    'first year'  => ['first year','1st year','1','year 1'],
                    'second year' => ['second year','2nd year','2','year 2'],
                    'third year'  => ['third year','3rd year','3','year 3'],
                    'fourth year' => ['fourth year','4th year','4','year 4'],
                ];
                $alts = $map[$val] ?? [$val];
                $q->where(function ($w) use ($alts) {
                    foreach ($alts as $a) {
                        $w->orWhereRaw('LOWER(TRIM(year_level)) = ?', [mb_strtolower($a)]);
                    }
                    $w->orWhereRaw('LOWER(TRIM(year_level)) LIKE ?', ['%'.mb_strtolower($alts[0]).'%']);
                });
            })
            ->when($this->semFilter !== '', function ($q) {
                $val = $this->norm($this->semFilter);
                $q->whereRaw('LOWER(TRIM(COALESCE(semester, ""))) = ?', [$val]);
            })
            ->paginate($this->perPage);
    }

    protected function baseDashQuery()
    {
        $q = Curriculum::query();

        if ($this->dashUsesFilters) {
            $s = trim($this->search);

            $q->when($s !== '', fn ($q) =>
                $q->where(function ($x) use ($s) {
                    $x->where('course_code', 'like', "%{$s}%")
                      ->orWhere('descriptive_title', 'like', "%{$s}%")
                      ->orWhere('pre_requisite', 'like', "%{$s}%");
                })
            )
            ->when($this->courseFilter, fn ($q) =>
                $q->where('course_id', (int) $this->courseFilter)
            )
            ->when($this->specFilter, fn ($q) =>
                $q->where('specialization_id', (int) $this->specFilter)
            )
            ->when($this->yearFilter !== '', function ($q) {
                $val = $this->norm($this->yearFilter);
                $map = [
                    'first year'  => ['first year','1st year','1','year 1'],
                    'second year' => ['second year','2nd year','2','year 2'],
                    'third year'  => ['third year','3rd year','3','year 3'],
                    'fourth year' => ['fourth year','4th year','4','year 4'],
                ];
                $alts = $map[$val] ?? [$val];
                $q->where(function ($w) use ($alts) {
                    foreach ($alts as $a) {
                        $w->orWhereRaw('LOWER(TRIM(year_level)) = ?', [mb_strtolower($a)]);
                    }
                    $w->orWhereRaw('LOWER(TRIM(year_level)) LIKE ?', ['%'.mb_strtolower($alts[0]).'%']);
                });
            })
            ->when($this->semFilter !== '', function ($q) {
                $val = $this->norm($this->semFilter);
                $q->whereRaw('LOWER(TRIM(COALESCE(semester, ""))) = ?', [$val]);
            });
        }

        return $q;
    }

    protected function dashboard(): array
    {
        $q = $this->baseDashQuery();

        $total            = (clone $q)->count();
        $totalUnits       = (clone $q)->sum('units');
        $withPrereq       = (clone $q)->whereNotNull('pre_requisite')->where('pre_requisite','!=','None')->count();
        $withLab          = (clone $q)->where('lab','>',0)->count();
        $distinctCourses  = (clone $q)->distinct('course_id')->count('course_id');

        $byYear = (clone $q)
            ->select('year_level', DB::raw('COUNT(*) as total'), DB::raw('SUM(COALESCE(units,0)) as units'))
            ->groupBy('year_level')
            ->get()
            ->map(function ($row) {
                $v = trim(mb_strtolower($row->year_level));
                $row->year_level = match (true) {
                    str_contains($v, '1') => '1st Year',
                    str_contains($v, '2') => '2nd Year',
                    str_contains($v, '3') => '3rd Year',
                    str_contains($v, '4') => '4th Year',
                    str_contains($v, 'first') => '1st Year',
                    str_contains($v, 'second') => '2nd Year',
                    str_contains($v, 'third') => '3rd Year',
                    str_contains($v, 'fourth') => '4th Year',
                    default => ucwords($v),
                };
                return $row;
            })
            ->groupBy('year_level')
            ->map(fn($group) => (object)[
                'year_level' => $group->first()->year_level,
                'total'      => $group->sum('total'),
                'units'      => $group->sum('units'),
            ])
            ->sortBy('year_level')
            ->values();

        $bySem = (clone $q)
            ->select(DB::raw("COALESCE(semester,'(None)') as semester"), DB::raw('COUNT(*) as total'))
            ->groupBy('semester')->orderBy('semester')->get();

        $topCourses = (clone $q)
            ->select('course_id', DB::raw('COUNT(*) as total'))
            ->groupBy('course_id')->with('course:id,course_name')->orderByDesc('total')->limit(5)->get();

        $recent = (clone $q)
            ->with(['course:id,course_name','specialization:id,name'])
            ->latest('id')->limit(8)->get();

        return [
            'stats' => [
                'total'            => $total,
                'total_units'      => (int) $totalUnits,
                'with_prereq'      => $withPrereq,
                'with_lab'         => $withLab,
                'distinct_courses' => $distinctCourses,
            ],
            'byYear'     => $byYear,
            'bySem'      => $bySem,
            'topCourses' => $topCourses,
            'recent'     => $recent,
        ];
    }

    public function render()
    {
        $yearLevels = Curriculum::query()
            ->select(DB::raw('TRIM(year_level) as yl'))
            ->whereNotNull('year_level')
            ->where('year_level', '!=', '')
            ->pluck('yl')
            ->map(function ($v) {
                $v = trim(mb_strtolower($v));
                return match (true) {
                    str_contains($v, '1') => '1st Year',
                    str_contains($v, '2') => '2nd Year',
                    str_contains($v, '3') => '3rd Year',
                    str_contains($v, '4') => '4th Year',
                    str_contains($v, 'first') => '1st Year',
                    str_contains($v, 'second') => '2nd Year',
                    str_contains($v, 'third') => '3rd Year',
                    str_contains($v, 'fourth') => '4th Year',
                    default => ucwords($v),
                };
            })
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($yearLevels)) {
            $yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
        }

        return view('livewire.registrar.curricula.index', [
            'items'           => $this->rows(),
            'courses'         => Course::orderBy('course_name')->get(['id','course_name as name']),
            'specializations' => Specialization::orderBy('name')->get(['id','name']),
            'yearLevels'      => $yearLevels,
            'semesters'       => ['1st Semester','2nd Semester','Summer'],
            'dash'            => $this->dashboard(),
        ]);
    }
}
