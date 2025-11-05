<?php

namespace App\Livewire\Head\Offerings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{CourseOffering, AcademicYear, Section, Course};
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

#[Title('Edit')]
#[Layout('layouts.head-shell')]
class Edit extends Component
{
    public CourseOffering $offering;

    public ?int $academic_id = null;
    public ?string $year_level = null;
    public ?int $section_id = null;
    public ?string $effectivity_year = null;

    /** course is locked/read-only */
    public ?int $courseIdLocked = null;

    /** select options */
    public array $academics = [];
    public array $levels = ['First Year', 'Second Year', 'Third Year', 'Fourth Year'];

    public ?string $currentAcademicLabel = null;
    public function mount(CourseOffering $offering): void
    {
        $this->offering = $offering->loadMissing('course', 'academicYear');

        // Block editing when locked (or pending if you want strict)
        if (in_array($this->offering->status, ['locked'/*, 'pending'*/], true)) {
            session()->flash('offerings_warning', 'This offering is not editable.');
            redirect()->route('head.offerings.index')->send();
            return;
        }

        // Load academics with whichever "semester" column exists
        $cols = ['id', 'school_year'];
        if (Schema::hasColumn('academic_years', 'semester_name')) {
            $cols[] = 'semester_name';
        } elseif (Schema::hasColumn('academic_years', 'semester')) {
            $cols[] = 'semester';
        }
        $this->academics = AcademicYear::orderByDesc('id')->get($cols)->toArray();

        // Lock the course to the offering's course_id (read-only)
        $this->courseIdLocked = (int) $offering->course_id;

        // Prefill form fields
        $this->academic_id      = $offering->academic_id;
        $this->year_level       = $offering->year_level;
        $this->section_id       = $offering->section_id;
        $this->effectivity_year = $offering->effectivity_year;

        $this->currentAcademicLabel =
        $this->formatFromModel()
        ?? $this->formatFromList($this->academic_id)
        ?? ($this->academic_id ? "AY # {$this->academic_id}" :null);
    }

    protected function formatFromModel() : ?string
    {

        $ay = $this->offering->academicYear;
        if (!$ay) return null;

        $ay = $ay->school_year ?? null;
        $sem = $ay->semester_name ?? ($ay->semester ?? null);

        return $sem ? " {$sem} - {$sem}" : ($sy ?? null);
    }
    protected function formatFromList(?int $id): ?string
    {
        if (!$id) return null;
        $row = collect($this->academics)->firstWhere('id', $id);
        if (!$row) return null;

        $sy  = $row['school_year'] ?? null;
        $sem = $row['semester_name'] ?? ($row['semester'] ?? null);

        return $sem ? "{$sy} — {$sem}" : ($sy ?? null);
    }


    /** Validation rules */
    protected function rules(): array
    {
        return [
            'year_level'  => ['required', Rule::in($this->levels)],
            'section_id'  => [
                'required',
                Rule::exists('sections', 'id')->where(function ($q) {
                    $q->where('course_id', $this->courseIdLocked);
                    if ($this->year_level) {
                        $q->where('year_level', $this->year_level);
                    }
                }),
            ],
            'effectivity_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'], // e.g., 2025-2026
        ];
    }

    /** Reset section when year level changes (to avoid stale/invalid selection) */
    public function updatedYearLevel(): void
    {
        // If current section is not in the new filtered list, clear it.
        $validIds = $this->sections->pluck('id')->all();
        if ($this->section_id && ! in_array($this->section_id, $validIds, true)) {
            $this->section_id = null;
        }
    }

    /** Computed: filtered sections based on LOCKED course + selected year level */
    public function getSectionsProperty()
    {
        return Section::query()
            ->where('course_id', $this->courseIdLocked)
            ->when($this->year_level, fn ($q) => $q->where('year_level', $this->year_level))
            ->orderBy('section_name')
            ->get(['id', 'section_name', 'year_level']);
    }

    public function save(): void
    {
        if (in_array($this->offering->status, ['locked'/*, 'pending'*/], true)) {
            session()->flash('offerings_warning', 'This offering is not editable.');
            redirect()->route('head.offerings.index');
            return;
        }

        $data = $this->validate();

        // Update ONLY editable fields (do NOT touch course_id)
        $this->offering->update([
            'year_level'       => $data['year_level'],
            'section_id'       => $data['section_id'],
            'effectivity_year' => $data['effectivity_year'] ?? null,
        ]);

        session()->flash('success', 'Course offering updated.');
        redirect()->route('head.offerings.index');
    }

    public function render()
    {
        return view('livewire.head.offerings.edit', [
            'academics' => $this->academics,
            'sections'  => $this->sections, // computed accessor above
            'levels'    => $this->levels,
            'offering'  => $this->offering,
        ]);
    }
}
