<?php

namespace App\Livewire\Head\Spec;

use Livewire\Component;;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Specialization;
use App\Models\Course;

#[Title('Add Specialization')]
#[Layout('layouts.head-shell')]
class Create extends Component
{
    public string $name = '';
    public ?int $course_id = null;

    protected function rules()
    {
        return [
            'name' => ['required','string','max:255','unique:specializations,name'],
            'course_id' => ['nullable','exists:courses,id'],
        ];
    }

    public function save()
    {
        $data = $this->validate();
        Specialization::create($data);

        session()->flash('success', 'Specialization created.');
        return redirect()->route('head.spec.index');
    }

    public function render()
    {
        return view('livewire.head.spec.create', [
            'courses' => Course::with('department')->get(),
        ]);
    }
}
