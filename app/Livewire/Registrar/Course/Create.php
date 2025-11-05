<?php

namespace App\Livewire\Registrar\Course;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Course;
use App\Models\Department;

#[Title('Add Course')]
#[Layout('layouts.registrar-shell')]
class Create extends Component
{
    public string $course_name = '';
    public string $course_description = '';
    public ?int $department_id = null;

    protected function rules()
    {
        return [
            'course_name' => ['required','string','max:255','unique:courses,course_name'],
            'course_description' => ['required','string','max:255'],
            'department_id' => ['required','exists:departments,id'],
        ];
    }

    public function save()
    {
        $data = $this->validate();
        Course::create($data);

        session()->flash('success', 'Course created.');
        return redirect()->route('registrar.course.index');
    }

    public function render()
    {
        return view('livewire.registrar.course.create', [
            'departments' => Department::all(),
        ]);
    }
}
