<?php

namespace App\Livewire\Head\Offerings\BlkGenerate;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\{AcademicYear, Course, Section, Curriculum, CourseOffering};

#[Title('Bulk Generate Offerings')]
#[Layout('layouts.head-shell')]
class Wizards extends Component
{
    public int $step = 1;

    // Step 1 inputs
    public ?int $academic_id = null;
    public ?int $course_id   = null;      // auto from Auth::user()->course_id
    public ?string $year_level = null;    // normalized to "1".."5"
    public ?string $effectivity_year = null; // optional override; defaults to academic SY

    // Derived term info
    public ?string $term_semester = null; // e.g., "1st Semester"
    public ?string $term_sy = null;       // e.g., "2024-2025"

    /** @var Collection<int, array> Suggested curricula subjects */
    public Collection $subjects;

    /** @var array<int,bool> section_id => checked */
    public array $sectionChecks = [];

    /** Allowed year-levels (DB usually stores numeric) */
    private array $ALLOWED_YL = ['1','2','3','4','5'];

    public function mount(): void
    {
        $this->subjects = collect();

        $user = Auth::user();
        $this->course_id = $user?->course_id;

        $active = AcademicYear::current();
        if (!$active) {
            session()->flash('error','No active term. Please ask Registrar to activate one.');
            return;
        }

        $this->academic_id   = $active->id;
        $this->term_sy       = $active->school_year;
        $this->term_semester = $active->semester;
        $this->effectivity_year ??= $active->school_year;

        // 🔧 Important: prefill year_level (UI may show "First Year" visually but not bind a value)
        if (!$this->year_level) {
            $this->year_level = '1';
        } else {
            $this->year_level = $this->normalizeYearLevel($this->year_level);
        }

        if (!$this->course_id) {
            session()->flash('error', 'Your account has no course assigned.');
        }
    }

    /** Livewire v3 updated hook for academic_id */
    public function updatedAcademicId(): void
    {
        if ($this->academic_id) {
            $this->loadTermInfo();
        }
    }

    /** Normalize immediately when user changes year_level */
    public function updatedYearLevel($value): void
    {
        $this->year_level = $this->normalizeYearLevel($value);
        // refresh the section checks whenever yl changes (if step permits)
        $this->initSectionChecks();
    }

    /** If course changes, refresh sections */
    public function updatedCourseId(): void
    {
        $this->initSectionChecks();
    }

    public function next()
    {
        if ($this->step === 1) {
            $this->validateStep1();              // ensures yl exists & normalized
            $this->loadTermInfo();
            $this->loadSuggestions();
            $this->initSectionChecks();
            $this->step = 2;
            return;
        }

        if ($this->step === 2) {
            if (!collect($this->sectionChecks)->filter()->count()) {
                $this->addError('sectionChecks', 'Please choose at least one section.');
                return;
            }
            $this->step = 3;
            return;
        }
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function generate()
    {
        $this->validateStep1();

        if (!collect($this->sectionChecks)->filter()->count()) {
            $this->addError('sectionChecks', 'Please choose at least one section.');
            return;
        }

        $acadId   = $this->academic_id;
        $courseId = $this->course_id;
        $yl       = $this->year_level;
        $eff      = $this->effectivity_year ?: $this->term_sy;
        $userId   = Auth::id();

        DB::transaction(function () use ($acadId, $courseId, $yl, $eff, $userId) {
            foreach ($this->sectionChecks as $sectionId => $on) {
                if (!$on) continue;

                $section = Section::find($sectionId);
                if (!$section || (int) $section->course_id !== (int) $courseId) continue;

                // 🔁 ensure uniqueness by (academic_id, section_id)
                $payload = [
                    'course_id'        => $courseId,
                    'year_level'       => $yl,
                    'effectivity_year' => $eff,
                    // ✅ show up in Registrar's queue
                    'status'           => 'pending', // was: 'draft'
                ];

                // Optional: fill request metadata if columns exist
                if (Schema::hasColumn('course_offerings', 'requested_by')) {
                    $payload['requested_by'] = $userId;
                }
                if (Schema::hasColumn('course_offerings', 'requested_at')) {
                    $payload['requested_at'] = now();
                }

                CourseOffering::updateOrCreate(
                    ['academic_id' => $acadId, 'section_id' => $sectionId],
                    $payload
                );
            }
        });

        session()->flash('success', 'Offerings generated and sent for approval.');
        return redirect()->route('head.offerings.index');
    }

    /* =========================
     * Helpers / Data Loading
     * ========================= */

    protected function validateStep1(): void
    {
        // Normalize first to avoid "required" false-negative when UI shows a label but value is empty
        $this->year_level = $this->normalizeYearLevel($this->year_level);

        $rules = [
            'course_id'        => ['required','exists:courses,id'],
            'year_level'       => ['required'],
            'effectivity_year' => ['nullable','string','max:255'],
        ];

        $messages = [
            'year_level.required' => 'Please choose a year level.',
        ];

        $this->validate($rules, $messages);

        // Enforce allowed range (1..5) after normalization
        if (!in_array($this->year_level, $this->ALLOWED_YL, true)) {
            $this->addError('year_level', 'Invalid year level selected.');
        }
    }

    protected function loadTermInfo(): void
    {
        $acad = AcademicYear::findOrFail($this->academic_id);
        $this->term_semester = $acad->semester;
        $this->term_sy       = $acad->school_year;
        if (!$this->effectivity_year) {
            $this->effectivity_year = $this->term_sy;
        }
    }

    protected function loadSuggestions(): void
    {
        $semNormalized = $this->normalizeSemester($this->term_semester); // "1" | "2" | "Summer"
        $ylNormalized  = $this->normalizeYearLevel($this->year_level);   // "1".."5"
        [$effStart, $effEnd] = $this->normalizeEffectivity($this->effectivity_year);

        // YEAR-LEVEL variants
        $ylVariants = $this->yearLevelVariants($ylNormalized, $this->year_level);

        // SEMESTER variants
        $semVariants = $this->semesterVariants($semNormalized, $this->term_semester);

        // Detect effectivity column (optional)
        $hasEffectivity     = Schema::hasColumn('curricula', 'effectivity_year');
        $hasEffectivityTypo = Schema::hasColumn('curricula', 'efectivity_year');
        $effCol = $hasEffectivity ? 'effectivity_year' : ($hasEffectivityTypo ? 'efectivity_year' : null);

        $q = Curriculum::query()
            ->where('course_id', $this->course_id)
            ->whereIn('year_level', $ylVariants)
            ->whereIn('semester', $semVariants);

        if ($effCol) {
            $q->where(function ($q) use ($effCol, $effStart, $effEnd) {
                $q->whereNull($effCol)
                  ->orWhere($effCol, '')
                  ->orWhere($effCol, $effStart);

                if ($effEnd) {
                    $q->orWhere($effCol, $effStart . '-' . $effEnd);
                } else {
                    $q->orWhere($effCol, $effStart . '-' . $effStart);
                }
            });
        }

        $this->subjects = $q->orderBy('course_code')
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'course_code' => $c->course_code,
                'title'       => $c->descriptive_title,
                'units'       => $c->units,
            ]);
    }

    protected function initSectionChecks(): void
    {
        // normalize & build variants (para mo-match bisan unsang porma sa DB)
        $ylNorm     = $this->normalizeYearLevel($this->year_level);
        $ylVariants = $this->yearLevelVariants($ylNorm, $this->year_level);

        if (!$this->course_id || !$ylNorm) {
            $this->sectionChecks = [];
            return;
        }

        $sections = Section::where('course_id', $this->course_id)
            ->whereIn('year_level', $ylVariants)  // 👈 key change (use variants)
            ->orderBy('section_name')
            ->get();

        $this->sectionChecks = [];
        foreach ($sections as $s) {
            $exists = CourseOffering::where('academic_id', $this->academic_id)
                ->where('section_id', $s->id)
                ->exists();
            $this->sectionChecks[$s->id] = !$exists;
        }
    }


    /* =========================
     * Normalizers
     * ========================= */

    protected function normalizeSemester(?string $sem): string
    {
        if (!$sem) return '';
        $s = strtolower(trim($sem));
        if (str_contains($s, '1')) return '1';
        if (str_contains($s, '2')) return '2';
        if (str_contains($s, 'sum')) return 'Summer';
        return ucfirst($s);
    }

    protected function normalizeYearLevel(?string $yl): string
    {
        if (!$yl) return '';
        $s = strtolower(trim((string)$yl));
        if (preg_match('/\d+/', $s, $m)) {
            return $m[0];
        }
        $map = [
            'first' => '1', '1st' => '1',
            'second' => '2', '2nd' => '2',
            'third' => '3', '3rd' => '3',
            'fourth' => '4', '4th' => '4',
            'fifth' => '5', '5th' => '5',
        ];
        foreach ($map as $k => $v) {
            if (str_contains($s, $k)) return $v;
        }
        return $yl;
    }

    /** Build variant forms of the semester to match "1", "1st", "1st Semester", "First Semester", etc. */
    protected function semesterVariants(string $normalized, ?string $raw): array
    {
        $variants = [];

        $map = [
            '1' => ['1', '1 Semester', '1st', '1st Semester', 'First', 'First Semester'],
            '2' => ['2', '2 Semester', '2nd', '2nd Semester', 'Second', 'Second Semester'],
            'Summer' => ['Summer', 'Summer Term'],
        ];

        if ($normalized !== '') {
            $variants = $map[$normalized] ?? [$normalized];
        }

        if ($raw && !in_array($raw, $variants, true)) {
            $variants[] = $raw;
        }

        return array_values(array_unique($variants));
    }

    protected function normalizeEffectivity(?string $eff): array
    {
        if (!$eff) return ['', null];
        $e = trim($eff);
        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $e, $m)) {
            return [$m[1], $m[2]];
        }
        if (preg_match('/^\d{4}$/', $e)) {
            return [$e, null];
        }
        return [$e, null];
    }

    /** Build variant forms of the year level to match "1", "1st Year", "First Year" etc. */
    protected function yearLevelVariants(string $normalized, ?string $raw): array
    {
        $variants = [];
        if ($normalized !== '') {
            $map = [
                '1' => ['1', '1st', 'First', '1st Year', 'First Year'],
                '2' => ['2', '2nd', 'Second', '2nd Year', 'Second Year'],
                '3' => ['3', '3rd', 'Third', '3rd Year', 'Third Year'],
                '4' => ['4', '4th', 'Fourth', '4th Year', 'Fourth Year'],
                '5' => ['5', '5th', 'Fifth', '5th Year', 'Fifth Year'],
            ];
            $variants = $map[$normalized] ?? [$normalized];
        }
        if ($raw && !in_array($raw, $variants, true)) {
            $variants[] = $raw;
        }
        return array_values(array_unique($variants));
    }

    public function render()
{
    $academics = AcademicYear::orderByDesc('id')->get();

    $ylNorm     = $this->normalizeYearLevel($this->year_level);
    $ylVariants = $this->yearLevelVariants($ylNorm, $this->year_level);

    $sections = ($this->course_id && $ylNorm)
        ? Section::where('course_id', $this->course_id)
            ->whereIn('year_level', $ylVariants)
            ->orderBy('section_name')
            ->get()
        : collect();

    $courseName = optional(Course::find($this->course_id))->course_name;

    return view('livewire.head.offerings.blk-generate.wizards', compact('academics', 'sections', 'courseName'));
}

}
