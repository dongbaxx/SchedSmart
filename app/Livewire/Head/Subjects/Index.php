<?php

namespace App\Livewire\Head\Subjects;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Curriculum;
use App\Models\Specialization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


#[Title('My Curricula')]
#[Layout('layouts.head-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $specFilter = null;
    public string $yearFilter = '';
    public string $semFilter = '';
    public int $perPage = 10;

    protected $queryString = [
        'search'     => ['except' => ''],
        'specFilter' => ['except' => null],
        'yearFilter' => ['except' => ''],
        'semFilter'  => ['except' => ''],
        'perPage'    => ['except' => 10],
        'page'       => ['except' => 1],
    ];

    protected function headCourseId(): int
    {
        $id = Auth::user()->course_id ?? null; // adjust
        abort_unless($id, 403, 'No course assigned to this head.');
        return (int) $id;
    }

    public function updated($name, $value): void
    {
        if ($name !== 'page') $this->resetPage();
        $this->dispatch('scroll-to-table');
    }

    protected function rows()
    {
        $courseId = $this->headCourseId();
        $s = trim($this->search);

        return Curriculum::query()
            ->where('course_id', $courseId) // ✅ lock to head course
            ->with(['specialization:id,name'])
            ->latest('id')
            ->when($s !== '', fn($q) =>
                $q->where(function ($x) use ($s) {
                    $x->where('course_code','like',"%{$s}%")
                      ->orWhere('descriptive_title','like',"%{$s}%")
                      ->orWhere('pre_requisite','like',"%{$s}%");
                })
            )
            ->when($this->specFilter, fn($q) => $q->where('specialization_id', (int)$this->specFilter))
            ->when($this->yearFilter !== '', fn($q) => $q->where('year_level', $this->yearFilter))
            ->when($this->semFilter !== '', fn($q) => $q->where('semester', $this->semFilter))
            ->paginate($this->perPage);
    }

    public function delete(int $id): void
    {
        $courseId = $this->headCourseId();

        $c = Curriculum::query()
            ->where('course_id', $courseId)   // ✅ cannot delete others
            ->withCount('facultyLoads')
            ->findOrFail($id);

        if ($c->faculty_loads_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Cannot delete: curriculum has assigned faculty loads.');
            return;
        }

        $c->delete();
        session()->flash('success', 'Curriculum deleted.');
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.head.subjects.index', [
            'items'           => $this->rows(),
            'specializations' => Specialization::orderBy('name')->get(['id','name']),
            'yearLevels'      => ['1st Year','2nd Year','3rd Year','4th Year'],
            'semesters'       => ['1st Semester','2nd Semester','Summer'],
        ]);
    }
}
