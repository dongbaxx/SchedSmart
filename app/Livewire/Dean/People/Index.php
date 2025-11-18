<?php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Course;

#[Title('Users & Roles')]
#[Layout('layouts.dean-shell')]
class Index extends Component
{
    use WithPagination;

    // URL-synced state
    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $role = null; // Dean/Head/Faculty

    #[Url(except: null)]
    public ?int $courseId = null;

    public function mount(): void
    {
        $u = Auth::user();
        abort_unless($u && $u->role === User::ROLE_DEAN, 403);
    }

    /** Reset pagination when filters change */
    public function updating($name, $value): void
    {
        if (in_array($name, ['search', 'role', 'courseId'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Edit existing Dean/Head/Faculty (open Dean People Create form with userId)
     */
    public function edit(int $userId): void
    {
        $this->redirectRoute('dean.people.create', ['userId' => $userId], navigate: true);
    }

    public function render()
    {
        $dean   = Auth::user();
        $deptId = (int) $dean->department_id;

        $q = User::query()
            ->with(['department', 'course', 'employment', 'userDepartment'])
            // IMPORTANT: apil na ang Dean sa list
            ->whereIn('role', [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY])
            // limit to dean's department diretso sa users.department_id
            ->where('department_id', $deptId)
            ->when($this->search !== '', function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when(filled($this->role), fn ($qq) => $qq->where('role', $this->role))
            ->when(filled($this->courseId), fn ($qq) => $qq->where('course_id', $this->courseId))
            ->orderBy('name');

        // roles dropdown (Dean can view Dean/Head/Faculty)
        $roles = [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY];

        // courses filter limited to Dean’s department
        $courses = Course::where('department_id', $deptId)
            ->orderBy('course_name')
            ->get(['id', 'course_name']);

        return view('livewire.dean.people.index', [
            'users'   => $q->paginate(12),
            'roles'   => $roles,
            'courses' => $courses,
        ]);
    }
}
