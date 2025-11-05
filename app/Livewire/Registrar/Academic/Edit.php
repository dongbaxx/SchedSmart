<?php

namespace App\Livewire\Registrar\Academic;

use Livewire\Component;
use App\Models\AcademicYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Edit Academic Term')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public int $academic_id;
    public string $school_year = '';
    public string $semester = '';

    protected $rules = [
        'school_year' => 'required|string|max:20',
        'semester'    => 'required|string|max:20',
    ];

    // IMPORTANT: param name {academic} sa route, type-hint AcademicYear
    public function mount(AcademicYear $academic): void
    {
        $this->academic_id = $academic->id;
        $this->school_year = $academic->school_year;
        $this->semester    = $academic->semester;
    }

    public function update()
    {
        $this->validate();

        AcademicYear::whereKey($this->academic_id)->update([
            'school_year' => $this->school_year,
            'semester'    => $this->semester,
        ]);

        session()->flash('success', 'Academic term updated successfully.');
        return redirect()->route('registrar.academic.index');
    }

    public function render()
    {
        return view('livewire.registrar.academic.edit');
    }
}
