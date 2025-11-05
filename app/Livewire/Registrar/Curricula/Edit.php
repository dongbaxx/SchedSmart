<?php

namespace App\Livewire\Registrar\Curricula;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{Curriculum, Course, Specialization};
use Illuminate\Validation\Rule;

#[Title('Edit Curriculum')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public Curriculum $curriculum;

    // scalar fields (same style as your Room edit)
    public $course_code = '';
    public $descriptive_title = '';
    public $units = null;
    public $lec = null;
    public $lab = null;
    public $cmo = null;
    public $hei = null;
    public $pre_requisite = 'None';
    public $course_id = '';
    public $year_level = '';
    public $specialization_id = '';
    public $efectivity_year = '';
    public $semester = '';

    // dropdown data
    public $courses = [];
    public $specializations = [];
    public array $yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
    public array $semesters  = ['1st Semester','2nd Semester','Summer'];

    public function mount(Curriculum $curriculum): void
    {
        // ✅ ensure mount receives the bound model
        $this->curriculum = $curriculum;

        // preload dropdowns
        $this->courses = Course::orderBy('course_name')->get(['id','course_name as name']);
        $this->specializations = Specialization::orderBy('name')->get(['id','name']);

        // ✅ prefill (cast IDs to string for <select>)
        $this->course_code        = (string) ($curriculum->course_code ?? '');
        $this->descriptive_title  = (string) ($curriculum->descriptive_title ?? '');
        $this->units              = is_null($curriculum->units) ? null : (int) $curriculum->units;
        $this->lec                = is_null($curriculum->lec) ? null : (int) $curriculum->lec;
        $this->lab                = is_null($curriculum->lab) ? null : (int) $curriculum->lab;
        $this->cmo                = is_null($curriculum->cmo) ? null : (int) $curriculum->cmo;
        $this->hei                = is_null($curriculum->hei) ? null : (int) $curriculum->hei;
        $this->pre_requisite      = (string) ($curriculum->pre_requisite ?? 'None');
        $this->course_id          = $curriculum->course_id ? (string) $curriculum->course_id : '';
        $this->year_level         = (string) ($curriculum->year_level ?? '');
        $this->specialization_id  = $curriculum->specialization_id ? (string) $curriculum->specialization_id : '';
        $this->efectivity_year    = (string) ($curriculum->efectivity_year ?? '');
        $this->semester           = (string) ($curriculum->semester ?? '');
    }

    protected function rules(): array
    {
        return [
            'course_code'        => ['required','string','max:255'],
            'descriptive_title'  => ['required','string','max:255'],
            'units'              => ['nullable','integer','min:0','max:255'],
            'lec'                => ['nullable','integer','min:0','max:255'],
            'lab'                => ['nullable','integer','min:0','max:255'],
            'cmo'                => ['nullable','integer','min:0','max:255'],
            'hei'                => ['nullable','integer','min:0','max:255'],
            'pre_requisite'      => ['nullable','string','max:255'],
            'course_id'          => ['required','integer', Rule::exists('courses','id')],
            'year_level'         => ['required','string','max:255'],
            'specialization_id'  => ['nullable','integer', Rule::exists('specializations','id')],
            'efectivity_year'    => ['nullable','string','max:255'],
            'semester'           => ['nullable','string','max:255'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->curriculum->update([
            'course_code'        => strtoupper(trim($data['course_code'])),
            'descriptive_title'  => trim($data['descriptive_title']),
            'units'              => $data['units'],
            'lec'                => $data['lec'],
            'lab'                => $data['lab'],
            'cmo'                => $data['cmo'],
            'hei'                => $data['hei'],
            'pre_requisite'      => $data['pre_requisite'] === '' ? 'None' : $data['pre_requisite'],
            'course_id'          => (int) $data['course_id'],
            'year_level'         => $data['year_level'],
            'specialization_id'  => $data['specialization_id'] ?: null,
            'efectivity_year'    => $data['efectivity_year'],
            'semester'           => $data['semester'],
        ]);

        session()->flash('ok', 'Curriculum updated.');
        redirect()->route('registrar.curricula.index');
    }

    public function render()
    {
        return view('livewire.registrar.curricula.edit');
    }
}
