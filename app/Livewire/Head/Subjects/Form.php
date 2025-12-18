<?php

namespace App\Livewire\Head\Subjects;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Curriculum;
use App\Models\Specialization;
use Illuminate\Support\Facades\Auth;


#[Title('Add Curriculum')]
#[Layout('layouts.head-shell')]
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
        'year_level'        => '',
        'specialization_id' => null,
        'efectivity_year'   => '',
        'semester'          => '',
    ];

    protected function headCourseId(): int
    {
        $id = Auth::user()->course_id ?? null; // adjust
        abort_unless($id, 403, 'No course assigned to this head.');
        return (int) $id;
    }

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
            'form.year_level'        => ['required','string','max:255'],
            'form.specialization_id' => ['nullable','integer','exists:specializations,id'],
            'form.efectivity_year'   => ['nullable','string','max:255'],
            'form.semester'          => ['nullable','string','max:255'],
        ];
    }

    protected function normalize(): void
    {
        foreach (['units','lec','lab','cmo','hei','specialization_id'] as $k) {
            $v = $this->form[$k] ?? null;
            $this->form[$k] = ($v === '' || $v === null) ? null : (int) $v;
        }

        $this->form['course_code'] = strtoupper(trim((string)$this->form['course_code']));
        $this->form['descriptive_title'] = trim((string)$this->form['descriptive_title']);
        $this->form['pre_requisite'] = trim((string)($this->form['pre_requisite'] ?? '')) ?: 'None';
    }

    public function save()
    {
        $this->validate();
        $this->normalize();

        Curriculum::create([
            ...$this->form,
            'course_id' => $this->headCourseId(), // ✅ force to head course
        ]);

        session()->flash('success', 'Curriculum Added Successfully');
        return redirect()->route('head.curricula.index');
    }

    public function render()
    {
        return view('livewire.head.subjects.form', [
            'specializations' => Specialization::orderBy('name')->get(['id','name']),
            'yearLevels'      => ['1st Year','2nd Year','3rd Year','4th Year'],
            'semesters'       => ['1st Semester','2nd Semester','Summer'],
        ]);
    }
}
