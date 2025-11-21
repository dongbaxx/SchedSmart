<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Course;
use App\Models\Department;

#[Title('My Faculty')]
#[Layout('layouts.head-shell')]
class Index extends Component
{
    use WithPagination;

    // Heads can search within their scoped list
    #[Url(as: 'search', except: '')]
    public string $search = '';

    /** @var int|null Course the Program Head handles */
    public ?int $myCourseId = null;

    /** @var bool */
    public bool $isHead = false;

    /** @var int|null Current Head user id */
    public ?int $myUserId = null;

    public function mount(): void
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();

        // treat either "Head" or legacy "Chairperson" as program head
        $this->isHead = in_array($me?->role, ['Head', 'Chairperson'], true);

        abort_unless($this->isHead, 403, 'Program Head only.');

        // lock to the Head's assigned course
        $this->myCourseId = $me?->course_id;
        $this->myUserId   = $me?->id;
        // If the Head has no course_id, keep page but show result as-is
    }

    public function updating($name, $value): void
    {
        if ($name === 'search') {
            $this->resetPage();
        }
    }

    /**
     * Edit existing faculty (or self) - open Head Faculties Create form with userId
     */
    public function edit(int $userId): void
    {
        $this->redirectRoute('head.faculties.create', ['userId' => $userId], navigate: true);
    }

    public function render()
    {
        $meId = $this->myUserId;

        $q = User::query()
            ->with(['department','course','employment','userDepartment'])
            // IMPORTANT: apil ang Head mismo sa list
            ->where(function ($qq) use ($meId) {
                $qq->where('role', 'Faculty');

                if ($meId) {
                    // apil ang current Head row bisan dili siya 'Faculty'
                    $qq->orWhere('id', $meId);
                }
            })
            ->when($this->myCourseId, fn ($qq) => $qq->where('course_id', $this->myCourseId))
            ->when($this->search !== '', function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name','like',"%{$s}%")
                      ->orWhere('email','like',"%{$s}%");
                });
            })
            ->orderBy('name');

        return view('livewire.head.faculties.index', [
            'users'       => $q->paginate(12),
            'course'      => $this->myCourseId ? Course::find($this->myCourseId) : null,
            'departments' => Department::orderBy('department_name')->get(['id','department_name']), // optional (shared UI)
        ]);
    }
}
