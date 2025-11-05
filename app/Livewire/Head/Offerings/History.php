<?php

namespace App\Livewire\Head\Offerings;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\{CourseOffering, AcademicYear};

#[Title('Offerings History')]
#[Layout('layouts.head-shell')]
class History extends Component
{
    use WithPagination;

    public ?int $academic_id = null;
    public ?string $year_level = null;
    public array $levels = ['First Year','Second Year','Third Year','Fourth Year'];

    public function updating($name, $value): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $courseId = $user->course_id;

        // active term id
        $activeId = AcademicYear::where('is_active', 1)->value('id');

        $rows = CourseOffering::query()
            ->with(['academicYear','course','section'])
            ->where('course_id', $courseId)
            ->when($activeId, fn($q) => $q->where('academic_id', '!=', $activeId)) // exclude active
            ->when($this->academic_id, fn($q) => $q->where('academic_id', $this->academic_id))
            ->when($this->year_level, fn($q) => $q->where('year_level', $this->year_level))
            ->orderByDesc('academic_id')
            ->paginate(10);

        $academics = AcademicYear::orderByDesc('id')->get();

        return view('livewire.head.offerings.history', compact('rows','academics'));
    }
}
