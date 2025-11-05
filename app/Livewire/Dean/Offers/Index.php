<?php

namespace App\Livewire\Dean\Offers;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use App\Models\{CourseOffering, AcademicYear, Course};

#[Title('Offerings')]
#[Layout('layouts.dean-shell')]
class Index extends Component
{
    use WithPagination;

    public ?int $academic_id = null;
    public ?int $course_id   = null;
    public ?string $year_level = null;

    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    /** 🔔 Listen to Laravel Echo broadcast so the table auto-refreshes */
    protected $listeners = [
        // same channel/event you broadcast from Head (OfferingCreated)
        'echo:offerings,OfferingCreated' => '$refresh',
    ];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $offering = CourseOffering::findOrFail($id);
        $offering->status = 'locked';
        $offering->save();

        session()->flash('offerings_success','Offering approved.');
        // snappy refresh
        $this->dispatch('$refresh');
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $offering = CourseOffering::findOrFail($id);
        if (($offering->status ?? '') === 'locked') {
            session()->flash('offerings_warning', 'Locked offerings cannot be deleted.');
            return;
        }
        $offering->delete();
        session()->flash('offerings_success','Offering deleted.');
        $this->dispatch('$refresh');
        $this->resetPage();
    }

    /** Map any “First Year / 1st / 1” into a variant array for robust filtering */
    protected function ylVariants(?string $yl): ?array
    {
        if (!$yl) return null;
        $key = strtolower(trim($yl));
        return match ($key) {
            'first year', 'first', '1st year', '1st', '1'   => ['1','1st','First','1st Year','First Year'],
            'second year','second','2nd year','2nd','2'     => ['2','2nd','Second','2nd Year','Second Year'],
            'third year', 'third', '3rd year','3rd','3'     => ['3','3rd','Third','3rd Year','Third Year'],
            'fourth year','fourth','4th year','4th','4'     => ['4','4th','Fourth','4th Year','Fourth Year'],
            'fifth year', 'fifth', '5th year','5th','5'     => ['5','5th','Fifth','5th Year','Fifth Year'],
            default => [$yl],
        };
    }

    protected function activeAcademicId(): ?int
    {
        return AcademicYear::where('is_active', 1)->value('id');
    }

    public function render()
    {
        $user = Auth::user();

        if (!$user || !$user->department_id) {
            session()->flash('error','No department associated with your account.');
            return view('livewire.dean.offers.index', [
                'rows' => collect(),
                'academics' => AcademicYear::orderByDesc('id')->get(),
                'courses' => collect(),
            ]);
        }

        $active = AcademicYear::current();
        if (!$active) {
            session()->flash('error','No active term. Please ask Registrar to activate one.');
            return view('livewire.dean.offers.index', [
                'rows' => collect(),
                'academics' => AcademicYear::orderByDesc('id')->get(),
                'courses' => collect(),
            ]);
        }

        $courses   = Course::where('department_id', $user->department_id)
                        ->orderBy('course_name')->get();
        $courseIds = $courses->pluck('id')->all();

        $academicId = $this->academic_id ?? $active->id;
        $ylVariants = $this->ylVariants($this->year_level);

        $rows = CourseOffering::query()
            ->with(['academic','course','section'])
            ->whereIn('course_id', $courseIds)
            ->when($academicId, fn($q) => $q->where('academic_id', $academicId))
            ->when($this->course_id, fn($q) => $q->where('course_id', $this->course_id))
            ->when($ylVariants, fn($q) => $q->whereIn('year_level', $ylVariants))
            ->where('status', '!=', 'archived')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.dean.offers.index', [
            'rows' => $rows,
            'academics' => AcademicYear::orderByDesc('id')->get(),
            'courses' => $courses,
        ]);
    }
}
