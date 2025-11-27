<?php

namespace App\Livewire\Registrar\Faculty;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Models\User;
use App\Models\Department;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

#[Title('Faculty & Users')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $role = null;

    #[Url(except: null)]
    public ?int $departmentId = null;

    #[Url(except: null)]
    public ?int $courseId = null;

    public function mount(): void
    {
        abort_unless(Auth::check() && Auth::user()->role === User::ROLE_REGISTRAR, 403);
    }

    public function updating($name, $value)
    {
        if (in_array($name, ['search', 'role', 'departmentId', 'courseId'], true)) {
            $this->resetPage();
        }
    }

    protected function getUsersQuery()
    {
        return User::query()
            ->with(['department', 'course', 'employment', 'userDepartment'])
            ->when($this->search !== '', function ($qq) {
                $s = trim($this->search);
                $qq->where(function ($w) use ($s) {
                    $w->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%");
                });
            })
            ->when($this->role, fn ($qq) => $qq->where('role', $this->role))
            ->when($this->departmentId, fn ($qq) => $qq->where('department_id', $this->departmentId))
            ->when($this->courseId, fn ($qq) => $qq->where('course_id', $this->courseId))
            ->orderBy('name');
    }

    public function create(): void
    {
        $this->redirectRoute('registrar.faculty.create', navigate: true);
    }

    public function render()
    {
        $q = $this->getUsersQuery();

        $roles = defined(User::class.'::ROLE_REGISTRAR')
            ? [
                User::ROLE_REGISTRAR,
                User::ROLE_DEAN,
                User::ROLE_HEAD,
                User::ROLE_FACULTY,
            ]
            : ['Registrar', 'Dean', 'Head', 'Faculty'];

        return view('livewire.registrar.faculty.index', [
            'users'       => $q->paginate(12),
            'departments' => Department::orderBy('department_name')->get(['id','department_name']),
            'courses'     => Course::orderBy('course_name')->get(['id','course_name']),
            'roles'       => $roles,
        ]);
    }
}
