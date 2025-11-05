<?php

namespace App\Livewire\Registrar\Academic;

use Livewire\Component;
use App\Models\AcademicYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Academic Terms')]
#[Layout('layouts.registrar-shell')]
class Add extends Component
{
    public string $school_year = '';
    public string $semester = '';

    protected $rules = [
        'school_year' => 'required|string|max:20',
        'semester'    => 'required|string|max:20',
    ];

    public function save()
    {
        $this->validate();

        AcademicYear::create([
            'school_year' => $this->school_year,
            'semester'    => $this->semester,
        ]);

        session()->flash('success','Academic term added.');
        return redirect()->route('registrar.academic.index');
    }

    public function render()
    {
        return view('livewire.registrar.academic.add');
    }
}
