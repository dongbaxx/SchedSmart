<?php

namespace App\Livewire\Registrar\Specialization;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Specialization;
use App\Models\Course;

#[Title('Edit Specialization')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public Specialization $specialization;
    public string $name = '';
    public ?int $course_id = null;

    protected function rules()
    {
        return [
            'name' => ['required','string','max:255','unique:specializations,name,' . $this->specialization->id],
            'course_id' => ['nullable','exists:courses,id'],
        ];
    }

    public function mount(Specialization $specialization)
    {
        $this->specialization = $specialization;
        $this->name = $specialization->name;
        $this->course_id = $specialization->course_id;
    }

    public function save()
    {
        $data = $this->validate();
        $this->specialization->update($data);

        session()->flash('success', 'Specialization updated.');
        return redirect()->route('registrar.specialization.index');
    }

    public function render()
    {
        return view('livewire.registrar.specialization.edit', [
            'courses' => Course::with('department')->get(),
        ]);
    }
}
