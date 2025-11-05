<?php

namespace App\Livewire\Head\Spec;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{Specialization, Course, Department};

#[Title('Specializations')]
#[Layout('layouts.head-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $departmentId = null;
    public ?int $courseId = null;
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'departmentId' => ['except' => null],
        'courseId' => ['except' => null],
        'page' => ['except' => 1],
    ];

    public function updating($name,$value)
    {
        if (in_array($name,['search','departmentId','courseId'])) {
            if ($name === 'departmentId') $this->courseId = null;
            $this->resetPage();
        }
    }

    public function delete(int $id)
    {
        Specialization::findOrFail($id)->delete();
        session()->flash('success','Specialization deleted.');
        $this->resetPage();
    }

    public function render()
    {
        $departments = Department::orderBy('department_name')->get();
        $courses = Course::when($this->departmentId, fn($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('course_name')->get();

        $items = Specialization::query()
            ->with('course.department')
            ->when($this->courseId, fn($q) => $q->where('course_id', $this->courseId))
            ->when($this->departmentId && !$this->courseId, function($q) {
                $courseIds = Course::where('department_id', $this->departmentId)->pluck('id');
                $q->whereIn('course_id', $courseIds);
            })
            ->when($this->search, fn($q) => $q->where('name','like',"%{$this->search}%"))
            ->orderBy('name')
            ->paginate($this->perPage);

        return view('livewire.head.spec.index', compact('items','departments','courses'));
    }
}

