<?php
// app/Livewire/Registrar/Offering/History.php

namespace App\Livewire\Registrar\Offering;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Models\{CourseOffering, AcademicYear, Course};

#[Title('Course Offerings • History')]
#[Layout('layouts.registrar-shell')]
class History extends Component
{
    use WithPagination;

    // filters
    public ?int $academic_id = null;
    public ?int $course_id   = null;
    public ?string $year_level = null;
    public ?string $status = 'archived'; // archived | locked | pending | draft | all
    public string $search = '';
    public bool $only_non_active_terms = true; // default: exclude active term

    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    // reset page on any filter change
    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $activeTermId = session('active_academic_id');

        $academics = AcademicYear::orderByDesc('id')->get();
        $courses   = Course::orderBy('course_name')->get();

        $rows = CourseOffering::query()
            ->with(['academicYear','course','section'])
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
            })
            ->orderByDesc('id')
            ->paginate(15);

        // quick stats
        $counts = CourseOffering::select('status', DB::raw('COUNT(*) as c'))
            ->when($activeTermId, fn($q)=>$q->where('academic_id','!=',$activeTermId))
            ->groupBy('status')->pluck('c','status')->toArray();

        return view('livewire.registrar.offering.history', compact('rows','academics','courses','counts'));
    }
}
