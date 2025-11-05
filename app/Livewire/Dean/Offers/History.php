<?php

namespace App\Livewire\Dean\Offers;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\{CourseOffering, AcademicYear, Course};

#[Title('History')]
#[Layout('layouts.dean-shell')]

class History extends Component
{
    use WithPagination;

    // filters
    public ?int $academic_id = null;
    public ?int $course_id   = null;
    public ?string $year_level = null;
    public ?string $status = 'archived';
    public string $search = '';
    public bool $only_non_active_terms = true;

    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        // require department for dean; if missing return empty dataset to view
        if (!$user || !$user->department_id) {
            $academics = AcademicYear::orderByDesc('id')->get();
            return view('livewire.dean.offers.history', [
                'rows' => collect(),
                'academics' => $academics,
                'courses' => collect(),
                'counts' => [],
            ]);
        }

        // Determine active term (prefer AcademicYear::current() if available)
        $active = AcademicYear::current();
        $activeTermId = $active ? $active->id : session('active_academic_id');

        // list academics and only courses under this dean's department
        $academics = AcademicYear::orderByDesc('id')->get();
        $courses = Course::where('department_id', $user->department_id)
                         ->orderBy('course_name')
                         ->get();
        $courseIds = $courses->pluck('id')->toArray();

        $query = CourseOffering::query()
            // relations used by the blade: academicYear, course, section
            ->with(['academicYear','course','section'])
            // restrict to courses under this dean's department
            ->whereIn('course_id', $courseIds)
            // history scope: non-active terms unless user disables it
            ->when($this->only_non_active_terms && $activeTermId, fn($q) =>
                $q->where('academic_id', '!=', $activeTermId)
            )
            // filters
            ->when($this->academic_id, fn($q) => $q->where('academic_id', $this->academic_id))
            ->when($this->course_id,   fn($q) => $q->where('course_id',   $this->course_id))
            ->when($this->year_level,  fn($q) => $q->where('year_level',  $this->year_level))
            ->when($this->status && $this->status !== 'all', fn($q) => $q->where('status', $this->status))
            ->when($this->search, function($q){
                $s = '%' . trim($this->search) . '%';
                $q->where(function($qq) use ($s){
                    $qq->whereHas('course', fn($cq)=>$cq->where('course_name','like',$s))
                       ->orWhereHas('section', fn($sq)=>$sq->where('section_name','like',$s))
                       ->orWhere('year_level','like',$s);
                });
            });

        $rows = $query->orderByDesc('id')->paginate(15);

        // quick stats (counts) with same department + non-active term restriction
        $countsQuery = CourseOffering::query()
            ->whereIn('course_id', $courseIds)
            ->when($this->only_non_active_terms && $activeTermId, fn($q)=>$q->where('academic_id','!=',$activeTermId));

        $counts = $countsQuery->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')->pluck('c','status')->toArray();

        return view('livewire.dean.offers.history', compact('rows','academics','courses','counts'));
    }
}
