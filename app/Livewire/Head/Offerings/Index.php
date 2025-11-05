<?php

namespace App\Livewire\Head\Offerings;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\{CourseOffering, AcademicYear, Course};

#[Title('Offerings')]
#[Layout('layouts.head-shell')]
class Index extends Component
{
    use WithPagination;

    public ?int $academic_id = null;
    public ?string $year_level = null;

    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    // 👂 Listen to Echo broadcast and refresh immediately
    protected $listeners = [
        'echo:offerings,OfferingStatusChanged' => '$refresh',
        // if you also emit OfferingCreated elsewhere:
        'echo:offerings,OfferingCreated' => '$refresh',
    ];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $offering = CourseOffering::findOrFail($id);
        if ($offering->status === 'locked') {
            session()->flash('offerings_warning', 'Locked offerings cannot be deleted.');
            return;
        }
        $offering->delete();
        session()->flash('offerings_success','Offering deleted.');
        $this->resetPage();
    }

    private function yearLevelVariants(?string $yl): ?array
    {
        if (!$yl) return null;
        $s = strtolower(trim($yl));
        return match ($s) {
            'first year','1','1st','1st year','first'   => ['1','1st','First','1st Year','First Year'],
            'second year','2','2nd','2nd year','second' => ['2','2nd','Second','2nd Year','Second Year'],
            'third year','3','3rd','3rd year','third'   => ['3','3rd','Third','3rd Year','Third Year'],
            'fourth year','4','4th','4th year','fourth' => ['4','4th','Fourth','4th Year','Fourth Year'],
            'fifth year','5','5th','5th year','fifth'   => ['5','5th','Fifth','5th Year','Fifth Year'],
            default => [$yl],
        };
    }

    public function render()
    {
        $user = Auth::user();

        $active = AcademicYear::current();
        $academics = AcademicYear::orderByDesc('id')->get();

        if (!$active) {
            session()->flash('error','No active term. Please ask Registrar to activate one.');
            return view('livewire.head.offerings.index', [
                'rows' => collect(),
                'academics' => $academics,
            ]);
        }

        $activeId = $this->academic_id ?: $active->id;
        $ylVariants = $this->yearLevelVariants($this->year_level);

        $rows = CourseOffering::query()
            // ✅ relation name must match what Blade uses: $r->academic?->...
            ->with(['academic','course','section'])
            ->where('course_id', $user->course_id)
            ->when($activeId, fn($q) => $q->where('academic_id', $activeId))
            ->when($ylVariants, fn($q) => $q->whereIn('year_level', $ylVariants))
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.head.offerings.index', [
            'rows' => $rows,
            'academics' => $academics,
        ]);
    }
}
