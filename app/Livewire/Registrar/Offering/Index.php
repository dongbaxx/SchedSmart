<?php

namespace App\Livewire\Registrar\Offering;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{CourseOffering, AcademicYear, Course};
use Illuminate\Support\Facades\Auth;

#[Title('Course Offerings')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    public ?int $academic_id = null;
    public ?int $course_id   = null;
    public ?string $year_level = null;

    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    /**
     * Listen to Laravel Echo broadcast:
     * channel: offerings
     * event:   OfferingCreated
     * action:  refresh table immediately
     */
    protected $listeners = [
        'echo:offerings,OfferingCreated' => '$refresh',
    ];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function approve(int $id): void
    {
        $offering = CourseOffering::findOrFail($id);
        $offering->update([
            'status'      => 'locked',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        session()->flash('offerings_success', 'Offering approved & locked.');
    }

    public function delete(int $id): void
    {
        $offering = CourseOffering::findOrFail($id);
        if ($offering->status === 'locked') {
            session()->flash('offerings_warning', 'Locked offerings cannot be deleted.');
            return;
        }
        $offering->delete();
        session()->flash('offerings_success', 'Offering deleted.');
        $this->resetPage();
    }

    protected function activeAcademicId(): ?int
    {
        // default sa active AY kung walay pinili sa filter
        return AcademicYear::where('is_active', 1)->value('id');
    }

    public function render()
    {
        $academics = AcademicYear::orderByDesc('id')->get();
        $courses   = Course::orderBy('course_name')->get();

        $activeId = $this->academic_id ?: AcademicYear::where('is_active', 1)->value('id');

        // map year-level filter to variants (handles "First Year" vs "1")
        $ylVariants = null;
        if ($this->year_level) {
            $ylVariants = match (strtolower($this->year_level)) {
                'first year', '1', '1st', '1st year', 'first'   => ['1','1st','First','1st Year','First Year'],
                'second year','2','2nd','2nd year','second'      => ['2','2nd','Second','2nd Year','Second Year'],
                'third year', '3','3rd','3rd year','third'       => ['3','3rd','Third','3rd Year','Third Year'],
                'fourth year','4','4th','4th year','fourth'      => ['4','4th','Fourth','4th Year','Fourth Year'],
                'fifth year', '5','5th','5th year','fifth'       => ['5','5th','Fifth','5th Year','Fifth Year'],
                default => [$this->year_level],
            };
        }

        $rows = CourseOffering::query()
            ->with(['academic','course','section'])
            ->when($activeId, fn($q) => $q->where('academic_id', $activeId))
            ->when($this->course_id, fn($q) => $q->where('course_id', $this->course_id))
            ->when($ylVariants, fn($q) => $q->whereIn('year_level', $ylVariants))
            ->where('status', '!=', 'archived')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.registrar.offering.index', compact('rows','academics','courses'));
    }

}
