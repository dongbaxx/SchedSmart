<?php

namespace App\Livewire\Registrar\Offering;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{CourseOffering, AcademicYear, Course, Section};
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema; // ⬅️ add this

#[Title('Edit Course Offering')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public CourseOffering $offering;

    public ?int $academic_id = null;
    public ?int $course_id = null;
    public ?string $year_level = null;
    public ?int $section_id = null;
    public ?string $effectivity_year = null;

    public $academics = [];
    public $courses = [];
    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    public function mount(CourseOffering $offering): void
    {
        $this->offering = $offering;

        // --- Load academics with whichever "semester" column exists ---
        $cols = ['id', 'school_year'];
        if (Schema::hasColumn('academic_years', 'semester_name')) {
            $cols[] = 'semester_name';
        } elseif (Schema::hasColumn('academic_years', 'semester')) {
            $cols[] = 'semester';
        }
        $this->academics = AcademicYear::orderByDesc('id')->get($cols);

        // Courses
        $this->courses = Course::orderBy('course_name')->get(['id','course_name']);

        // Prefill
        $this->academic_id      = $offering->academic_id;
        $this->course_id        = $offering->course_id;
        $this->year_level       = $offering->year_level;
        $this->section_id       = $offering->section_id;
        $this->effectivity_year = $offering->effectivity_year;
    }

    // Reset section when course/year changes
    public function updatedCourseId(): void { $this->section_id = null; }
    public function updatedYearLevel(): void { $this->section_id = null; }

    // Sections computed
    public function getSectionsProperty()
    {
        if (!$this->course_id || !$this->year_level) return collect();

        return Section::query()
            ->where('course_id', $this->course_id)
            ->where('year_level', $this->year_level)
            ->orderBy('section_name')
            ->get(['id','section_name']);
    }

    protected function rules(): array
    {
        return [
            'academic_id'      => ['required','integer', Rule::exists('academic_years','id')],
            'course_id'        => ['required','integer', Rule::exists('courses','id')],
            'year_level'       => ['required','string'],
            'section_id'       => ['required','integer', Rule::exists('sections','id')],
            'effectivity_year' => ['nullable','string','max:50'],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $this->offering->update([
            'academic_id'      => $data['academic_id'],
            'course_id'        => $data['course_id'],
            'year_level'       => $data['year_level'],
            'section_id'       => $data['section_id'],
            'effectivity_year' => $data['effectivity_year'],
        ]);

        session()->flash('ok', 'Course offering updated.');
        redirect()->route('registrar.offering.index');
    }

    public function render()
    {
        return view('livewire.registrar.offering.edit', [
            'academics' => $this->academics,
            'courses'   => $this->courses,
            'sections'  => $this->sections,
            'levels'    => $this->levels,
        ]);
    }
}
