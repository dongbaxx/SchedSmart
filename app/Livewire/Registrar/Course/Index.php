<?php

namespace App\Livewire\Registrar\Course;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{Course, Department};

#[Title('Courses/Programs')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $departmentId = null;
    public int $perPage = 10;

    protected $queryString = [
        'search'       => ['except' => ''],
        'departmentId' => ['except' => null],
    ];

    // Optional: kung mag-type ug search, mo-apply lang ni kung WALA’y department
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Explicit handler para sa select change */
    public function pickDepartment($value): void
    {
        $this->departmentId = ($value === '' || $value === null) ? null : (int) $value;

        // Basta naay department, i-ignore nato ang search (clear it)
        if (!is_null($this->departmentId)) {
            $this->search = '';
        }

        $this->resetPage();
    }

    public function delete(int $id): void
    {
        Course::findOrFail($id)->delete();
        session()->flash('success', 'Course deleted.');
        $this->resetPage();
    }

    public function render()
    {
        $departments = Department::orderBy('department_name')->get();

        $deptId = $this->departmentId;
        $term   = trim($this->search);

        $items = Course::query()
            ->with('department')

            // If naa’y napiling department -> i-filter tanan courses under it
            ->when(!is_null($deptId), fn ($q) =>
                $q->where('department_id', $deptId)
            )

            // Kung walay department, allowed ang search
            ->when(is_null($deptId) && $term !== '', function ($q) use ($term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('course_name', 'like', "%{$term}%")
                       ->orWhere('course_description', 'like', "%{$term}%")
                       ->orWhereHas('department', fn ($d) =>
                           $d->where('department_name', 'like', "%{$term}%")
                       );
                });
            })

            ->orderBy('course_name')
            ->paginate($this->perPage);

        return view('livewire.registrar.course.index', compact('items', 'departments'));
    }
}
