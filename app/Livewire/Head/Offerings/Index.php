<?php

namespace App\Livewire\Head\Offerings;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;

use App\Models\{CourseOffering, AcademicYear};

#[Title('Offerings')]
#[Layout('layouts.head-shell')]
class Index extends Component
{
    use WithPagination;

    public ?int $academic_id = null;

    // ✅ filter pending
    public bool $pendingOnly = false;

    protected $queryString = [
        'academic_id' => ['except' => ''],
        'pendingOnly' => ['except' => false],
    ];

    protected $listeners = [
        'echo:offerings,OfferingStatusChanged' => '$refresh',
        'echo:offerings,OfferingCreated' => '$refresh',
    ];

    public function togglePending(): void
    {
        $this->pendingOnly = !$this->pendingOnly;
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();

        $active = AcademicYear::current();
        $academics = AcademicYear::orderByDesc('id')->get();

        if (!$active) {
            session()->flash('error', 'No active term. Please ask Registrar to activate one.');
            return view('livewire.head.offerings.index', [
                'rows' => collect(),
                'academics' => $academics,
            ]);
        }

        $activeId = $this->academic_id ?: $active->id;

        // ✅ get offerings then group by academic term
        $offerings = CourseOffering::query()
            ->with(['academic'])
            ->where('course_id', $user->course_id)
            ->when($activeId, fn($q) => $q->where('academic_id', $activeId))
            ->when($this->pendingOnly, fn($q) => $q->where('status', 'pending'))
            ->orderByDesc('id')
            ->get();

        $rows = $offerings
            ->groupBy('academic_id')
            ->map(function ($items) {
                $a = $items->first()->academic;

                $pending = $items->where('status', 'pending')->count();
                $locked  = $items->where('status', 'locked')->count();

                return (object)[
                    'academic_id' => (int)($items->first()->academic_id ?? 0),
                    'term_label'  => ($a?->school_year ?? '-') . ' — ' . ($a?->semester ?? '-'),

                    // ✅ always display ALL
                    'year_level_label' => 'ALL',
                    'section_label'    => 'ALL',

                    // counts
                    'pending_count' => $pending,
                    'locked_count'  => $locked,
                    'total'         => $items->count(),

                    // ✅ can schedule if naay locked sections
                    'can_schedule'  => $locked > 0,
                ];
            })
            ->values();

        // ✅ paginate grouped rows
        $perPage = 10;
        $page = $this->getPage();
        $paged = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $rows = new \Illuminate\Pagination\LengthAwarePaginator(
            $paged,
            $rows->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.head.offerings.index', [
            'rows' => $rows,
            'academics' => $academics,
        ]);
    }
}
