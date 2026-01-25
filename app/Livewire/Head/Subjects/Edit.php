<?php

namespace App\Livewire\Head\Subjects;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Curriculum;
use App\Models\Specialization;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;


#[Title('Edit Curriculum')]
#[Layout('layouts.head-shell')]
class Edit extends Component
{
    public Curriculum $curriculum;

    public $course_code = '';
    public $descriptive_title = '';
    public $units = null;
    public $lec = null;
    public $lab = null;
    public $cmo = null;
    public $hei = null;
    public $pre_requisite = 'None';
    public $year_level = '';
    public $specialization_id = '';
    public $efectivity_year = '';
    public $semester = '';

    public $specializations = [];
    public array $yearLevels = ['1st Year','2nd Year','3rd Year','4th Year'];
    public array $semesters  = ['1st Semester','2nd Semester','Summer'];

    protected function headCourseId(): int
    {
        $id = Auth::user()->course_id ?? null; // adjust
        abort_unless($id, 403, 'No course assigned to this head.');
        return (int) $id;
    }

    public function mount(Curriculum $curriculum): void
    {
        // ✅ authorize by course ownership
        abort_unless($curriculum->course_id === $this->headCourseId(), 403);

        $this->curriculum = $curriculum;

        $this->specializations = Specialization::orderBy('name')->get(['id','name']);

        $this->course_code       = (string) ($curriculum->course_code ?? '');
        $this->descriptive_title = (string) ($curriculum->descriptive_title ?? '');
        $this->units             = is_null($curriculum->units) ? null : (int) $curriculum->units;
        $this->lec               = is_null($curriculum->lec) ? null : (int) $curriculum->lec;
        $this->lab               = is_null($curriculum->lab) ? null : (int) $curriculum->lab;
        $this->cmo               = is_null($curriculum->cmo) ? null : (int) $curriculum->cmo;
        $this->hei               = is_null($curriculum->hei) ? null : (int) $curriculum->hei;
        $this->pre_requisite     = (string) ($curriculum->pre_requisite ?? 'None');
        $this->year_level        = (string) ($curriculum->year_level ?? '');
        $this->specialization_id = $curriculum->specialization_id ? (string)$curriculum->specialization_id : '';
        $this->efectivity_year   = (string) ($curriculum->efectivity_year ?? '');
        $this->semester          = (string) ($curriculum->semester ?? '');
    }

    protected function rules(): array
    {
        return [
            'course_code'       => ['required','string','max:255'],
            'descriptive_title' => ['required','string','max:255'],
            'units'             => ['nullable','integer','min:0','max:255'],
            'lec'               => ['nullable','integer','min:0','max:255'],
            'lab'               => ['nullable','integer','min:0','max:255'],
            'cmo'               => ['nullable','integer','min:0','max:255'],
            'hei'               => ['nullable','integer','min:0','max:255'],
            'pre_requisite'     => ['nullable','string','max:255'],
            'year_level'        => ['required','string','max:255'],
            'specialization_id' => ['nullable','integer', Rule::exists('specializations','id')],
            'efectivity_year'   => ['nullable','string','max:255'],
            'semester'          => ['nullable','string','max:255'],
        ];
    }

    public function save()
    {
        $data = $this->validate();

        // ✅ re-check ownership before update
        abort_unless($this->curriculum->course_id === $this->headCourseId(), 403);

        $data['units'] = $data['units'] === '' ? null : $data['units'];
        $data['lec']   = $data['lec'] === '' ? null : $data['lec'];
        $data['lab']   = $data['lab'] === '' ? null : $data['lab'];
        $data['cmo']   = $data['cmo'] === '' ? null : $data['cmo'];
        $data['hei']   = $data['hei'] === '' ? null : $data['hei'];

        $this->curriculum->update([
            'course_code'       => strtoupper(trim($data['course_code'])),
            'descriptive_title' => trim($data['descriptive_title']),
            'units'             => $data['units'],
            'lec'               => $data['lec'],
            'lab'               => $data['lab'],
            'cmo'               => $data['cmo'],
            'hei'               => $data['hei'],
            'pre_requisite'     => trim((string)($data['pre_requisite'] ?? '')) ?: 'None',
            'year_level'        => $data['year_level'],
            'specialization_id' => ($data['specialization_id'] ?? null) ? (int)$data['specialization_id'] : null,
            'efectivity_year'   => $data['efectivity_year'],
            'semester'          => $data['semester'],
        ]);

        session()->flash('success', 'Curriculum updated.');
    return redirect()->route('head.subjects.index');
    }

    public function render()
    {
        return view('livewire.head.subjects.edit');
    }
}
