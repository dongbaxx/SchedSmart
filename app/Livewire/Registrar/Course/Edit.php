<?php

namespace App\Livewire\Registrar\Course;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Course;
use App\Models\Department;

#[Title('Edit Course')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public Course $course;
    public string $course_name = '';
    public string $course_description = '';
    public ?int $department_id = null;

    protected function rules()
    {
        return [
            'course_name' => ['required','string','max:255','unique:courses,course_name,' . $this->course->id],
            'course_description' => ['required','string','max:255'],
            'department_id' => ['required','exists:departments,id'],
        ];
    }

    public function mount(Course $course)
    {
        $this->course = $course;
        $this->course_name = $course->course_name;
        $this->course_description = $course->course_description;
        $this->department_id = $course->department_id;
    }

    public function save()
    {
        $data = $this->validate();
        $this->course->update($data);

        session()->flash('success', 'Course updated.');
        return redirect()->route('registrar.course.index');
    }

    public function render()
    {
        return view('livewire.registrar.course.edit', [
            'departments' => Department::all(),
        ]);
    }
}
