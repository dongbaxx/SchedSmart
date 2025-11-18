<?php

// app/Livewire/Dean/People/Create.php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use App\Models\Course;
use App\Models\Department;

#[Title('Add/Edit Head or Faculty')]
#[Layout('layouts.dean-shell')]
class Create extends Component
{
    public ?int $userId = null;

    public string $name = '';
    public string $email = '';
    public ?string $role = null;   // Head | Faculty
    public ?string $password = null;
    public string $password_confirmation = '';

    public ?int $course_id = null;     // bound to select

    /** Dean's department (locked) */
    public int $department_id;

    public function mount(?int $userId = null): void
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        // Dean's department is fixed
        $this->department_id = (int) $dean->department_id;

        // Resolve userId from:
        // - Livewire param
        // - route param {userId} or {user}
        // - query string ?userId=123
        $routeId = request()->route('userId')
            ?? request()->route('user')
            ?? request()->integer('userId');

        $this->userId = $userId ?? (is_numeric($routeId) ? (int) $routeId : null);

        // If editing, load existing data so form is NOT empty
        if ($this->userId) {
            $u = User::whereIn('role', [User::ROLE_HEAD, User::ROLE_FACULTY])
                ->where('department_id', $this->department_id)
                ->findOrFail($this->userId);

            $this->name      = (string) ($u->name ?? '');
            $this->email     = (string) ($u->email ?? '');
            $this->role      = $u->role ?? null;
            $this->course_id = $u->course_id;   // ❗ this preselects the Course / Program
        }
    }

    public function updated(string $name, $value): void
    {
        // Optional alias: "Chairperson" → HEAD
        if ($name === 'role' && $value === 'Chairperson') {
            $this->role = User::ROLE_HEAD;
        }
    }

    public function save(): void
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        $allowedRoles = [User::ROLE_HEAD, User::ROLE_FACULTY];

        $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->userId),
            ],
            'role'  => ['required', Rule::in($allowedRoles)],

            // password required kung create; optional kung edit
            'password' => [
                $this->userId ? 'nullable' : 'required',
                'confirmed',
                Password::defaults(),
            ],

            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->where(
                    fn ($q) => $q->where('department_id', $this->department_id)
                ),
            ],
        ]);

        $data = [
            'name'          => $this->name,
            'email'         => $this->email,
            'role'          => $this->role,
            'department_id' => $this->department_id,
            'course_id'     => $this->course_id,
        ];

        if (filled($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $user = User::updateOrCreate(['id' => $this->userId], $data);
        $this->userId = $user->id;

        session()->flash('ok', 'Head/Faculty saved successfully.');
        $this->redirectRoute('dean.people.index', navigate: true);
    }

    public function render()
    {
        $deptId = $this->department_id;

        // Only courses under Dean's department
        $courses = Course::where('department_id', $deptId)
            ->orderBy('course_name')
            ->get(['id', 'course_name']);

        $roles = [User::ROLE_HEAD, User::ROLE_FACULTY];

        $departmentName = Department::find($deptId)?->department_name ?? '—';

        return view('livewire.dean.people.create', [
            'courses'        => $courses,
            'roles'          => $roles,
            'departmentName' => $departmentName,
        ]);
    }
}
