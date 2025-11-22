<?php

namespace App\Livewire\Dean\Special;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\{Specialization, Course, Department};

#[Title('Specializations')]
#[Layout('layouts.dean-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $department_id = null;
    public ?int $course_id = null;
    public int $perPage = 10;

    public function updating($field)
    {
        if (in_array($field, ['search', 'department_id', 'course_id'])) {

            // kung mag-ilis kag department, i-reset ang course
            if ($field === 'department_id') {
                $this->course_id = null;
            }

            $this->resetPage();
        }
    }

    public function delete(int $id)
    {
        Specialization::findOrFail($id)->delete();
        session()->flash('success', 'Specialization deleted.');
        $this->resetPage();
    }

    public function render()
    {
        // dropdown data
        $departments = Department::orderBy('department_name')->get();

        // courses filtered by selected department (same pattern sa Sections)
        $courses = Course::when($this->department_id, function ($q) {
                return $q->where('department_id', $this->department_id);
            })
            ->orderBy('course_name')
            ->get();

        $rows = Specialization::with('course.department')
            // search by specialization name
            ->when($this->search, fn($q) =>
                $q->where('name', 'like', "%{$this->search}%")
            )

            // 🔹 filter by DEPARTMENT = tanang specializations sa mga course under ana nga department
            ->when($this->department_id, function ($q) {
                $q->whereHas('course', function ($cq) {
                    $cq->where('department_id', $this->department_id);
                });
            })

            // 🔹 filter by COURSE = specializations sa ana nga course
            ->when($this->course_id, fn($q) =>
                $q->where('course_id', $this->course_id)
            )

            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.dean.special.index', compact('rows', 'departments', 'courses'));
    }
}
