<?php

// app/Livewire/Dean/People/Index.php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Course};

#[Title('Heads & Faculty')]
#[Layout('layouts.dean-shell')]
class Index extends Component
{
    use WithPagination;

    // URL-synced state
    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $role = null; // Head/Faculty

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
        if (in_array($name, ['search','role','courseId'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $dean   = Auth::user();
        $deptId = (int) $dean->department_id;

        $q = User::query()
            ->with(['department','course','employment','userDepartment'])
            ->whereIn('role', [User::ROLE_HEAD, User::ROLE_FACULTY])
            ->inDepartment($deptId) // scope you added; or inline when()
            ->when($this->search !== '', function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name','like',"%{$s}%")
                      ->orWhere('email','like',"%{$s}%");
                });
            })
            ->when($this->role, fn ($qq) => $qq->where('role', $this->role))
            ->when($this->courseId, fn ($qq) => $qq->where('course_id', $this->courseId))
            ->orderBy('name');

        // roles dropdown (Dean can view Head/Faculty only)
        $roles = [User::ROLE_HEAD, User::ROLE_FACULTY];

        // courses filter limited to Dean’s department
        $courses = Course::where('department_id', $deptId)
            ->orderBy('course_name')
            ->get(['id','course_name']);

        return view('livewire.dean.people.index', [
            'users'   => $q->paginate(12),
            'roles'   => $roles,
            'courses' => $courses,
        ]);
    }
}
