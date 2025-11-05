<?php

namespace App\Livewire\Registrar\Curricula;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Specialization;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

#[Title('Add Curriculum')]
#[Layout('layouts.registrar-shell')]
class Form extends Component
{
    public array $form = [
        'course_code'       => '',
        'descriptive_title' => '',
        'units'             => null,
        'lec'               => null,
        'lab'               => null,
        'cmo'               => null,
        'hei'               => null,
        'pre_requisite'     => 'None',
        'course_id'         => null,
        'year_level'        => '',
        'specialization_id' => null,
        'efectivity_year'   => '',
        'semester'          => '',
    ];

    public function rules(): array
    {
        return [
            'form.course_code'       => ['required','string','max:255'],
            'form.descriptive_title' => ['required','string','max:255'],
            'form.units'             => ['nullable','integer','min:0','max:255'],
            'form.lec'               => ['nullable','integer','min:0','max:255'],
            'form.lab'               => ['nullable','integer','min:0','max:255'],
            'form.cmo'               => ['nullable','integer','min:0','max:255'],
            'form.hei'               => ['nullable','integer','min:0','max:255'],
            'form.pre_requisite'     => ['nullable','string','max:255'],
            'form.course_id'         => ['required','integer','exists:courses,id'],
            'form.year_level'        => ['required','string','max:255'],
            'form.specialization_id' => ['nullable','integer','exists:specializations,id'],
            'form.efectivity_year'   => ['nullable','string','max:255'], // keep the table spelling
            'form.semester'          => ['nullable','string','max:255'],
        ];
    }

    /** Convert empty strings from selects/inputs into nulls for nullable integer fields */
    protected function normalize(): void
    {
        foreach (['units','lec','lab','cmo','hei','course_id','specialization_id'] as $k) {
            $v = $this->form[$k] ?? null;
            $this->form[$k] = ($v === '' || $v === null) ? null : (int) $v;
        }

        foreach (['pre_requisite','efectivity_year','semester','year_level','course_code','descriptive_title'] as $k) {
            if (($this->form[$k] ?? null) === '') $this->form[$k] = null;
        }
    }

    public function save()
    {
        $this->validate();
        $this->normalize();

        Curriculum::create($this->form);

        session()->flash('ok', 'Curriculum Added Successfully');
        return redirect()->route('registrar.curricula.index');
    }

    public function render()
    {
        $courses         = Course::orderBy('course_name')->get(['id','course_name as name']);
        $specializations = Specialization::orderBy('name')->get(['id','name']);

        $yearLevels = ['1st Year','2nd Year','3rd Year','4rth Year'];
        $semesters  = ['1st Semester','2nd Semester','Summer'];

        return view('livewire.registrar.curricula.form', compact(
            'courses','specializations','yearLevels','semesters'
        ));
    }
}
