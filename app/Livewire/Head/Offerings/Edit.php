<?php

namespace App\Livewire\Head\Offerings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{CourseOffering, AcademicYear, Section};
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

#[Title('Edit Course Offering')]
#[Layout('layouts.head-shell')]
class Edit extends Component
{
    public CourseOffering $offering;

    public ?int $academic_id = null;

    // Walay type hint para smooth ang Livewire binding
    public $year_level = null;   // 1..4 gikan sa select
    public $section_id = null;   // section id

    public ?string $effectivity_year = null;

    public ?int $courseIdLocked = null;

    public array $academics = [];

    public array $levels = [
        1 => 'First Year',
        2 => 'Second Year',
        3 => 'Third Year',
        4 => 'Fourth Year',
    ];

    public ?string $currentAcademicLabel = null;

    public function mount(CourseOffering $offering): void
    {
        $this->offering = $offering->loadMissing('course', 'academicYear');

        if (in_array($this->offering->status, ['locked'], true)) {
            session()->flash('offerings_warning', 'This offering is not editable.');
            redirect()->route('head.offerings.index')->send();
            return;
        }

        // AY list
        $cols = ['id', 'school_year'];
        if (Schema::hasColumn('academic_years', 'semester_name')) {
            $cols[] = 'semester_name';
        } elseif (Schema::hasColumn('academic_years', 'semester')) {
            $cols[] = 'semester';
        }
        $this->academics = AcademicYear::orderByDesc('id')->get($cols)->toArray();

        // lock course
        $this->courseIdLocked = (int) $offering->course_id;

        // prefill
        $this->academic_id = $offering->academic_id;
        $this->year_level  = $offering->year_level ? (int) $offering->year_level : null;
        $this->section_id  = $offering->section_id;
        $this->effectivity_year = $offering->effectivity_year;

        $this->currentAcademicLabel =
            $this->formatFromModel()
            ?? $this->formatFromList($this->academic_id)
            ?? ($this->academic_id ? "AY # {$this->academic_id}" : null);
    }

    protected function formatFromModel(): ?string
    {
        $ay = $this->offering->academicYear;
        if (! $ay) {
            return null;
        }

        $sy  = $ay->school_year ?? null;
        $sem = $ay->semester_name ?? ($ay->semester ?? null);

        return $sem ? "{$sy} — {$sem}" : ($sy ?? null);
    }

    protected function formatFromList(?int $id): ?string
    {
        if (! $id) return null;

        $row = collect($this->academics)->firstWhere('id', $id);
        if (! $row) return null;

        $sy  = $row['school_year'] ?? null;
        $sem = $row['semester_name'] ?? ($row['semester'] ?? null);

        return $sem ? "{$sy} — {$sem}" : ($sy ?? null);
    }

    protected function rules(): array
    {
        return [
            'year_level' => ['required', Rule::in(array_keys($this->levels))],
            'section_id' => ['required', 'exists:sections,id'],
            'effectivity_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
        ];
    }

    public function updatedYearLevel(): void
    {
        // kung usbon Year Level, i-clear ang section
        $this->section_id = null;
    }

    public function save()
    {
        if (in_array($this->offering->status, ['locked'], true)) {
            session()->flash('offerings_warning', 'This offering is not editable.');
            return redirect()->route('head.offerings.index');
        }

        $data = $this->validate();

        $this->offering->update([
            'year_level'       => (int) $data['year_level'],
            'section_id'       => $data['section_id'],
            'effectivity_year' => $data['effectivity_year'] ?? null,
        ]);

        session()->flash('success', 'Course offering updated.');
        return redirect()->route('head.offerings.index');
    }

    public function render()
    {
        // **Dili na ta mag-filter diri** → kuhaon nato TANAN sections sa course
        $sections = Section::query()
            ->where('course_id', $this->courseIdLocked)
            ->orderBy('section_name')
            ->get();

        $currentLevelInt = $this->year_level
            ? (int) $this->year_level
            : ($this->offering->year_level ? (int) $this->offering->year_level : null);

        $yearLevelLabel = $currentLevelInt
            ? ($this->levels[$currentLevelInt] ?? $currentLevelInt)
            : null;

        return view('livewire.head.offerings.edit', [
            'academics'      => $this->academics,
            'sections'       => $sections,     // Blade na ang mo-filter by year
            'levels'         => $this->levels,
            'offering'       => $this->offering,
            'academicLabel'  => $this->currentAcademicLabel,
            'yearLevelLabel' => $yearLevelLabel,
        ]);
    }
}
