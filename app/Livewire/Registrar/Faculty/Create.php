<?php

namespace App\Livewire\Registrar\Faculty;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Department;
use App\Models\Course;

#[Title('Add User')]
#[Layout('layouts.registrar-shell')]
class Create extends Component
{
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public ?string $role = null; // nullable so easier to detect "no selection"
    public ?string $password = null;
    public string $password_confirmation = '';

    public ?int $department_id = null;
    public ?int $course_id = null;

    public function mount(?int $userId = null): void
    {
        abort_unless(Auth::check() && Auth::user()->role === User::ROLE_REGISTRAR, 403);

        $this->userId = $userId;

        if ($userId) {
            $u = User::findOrFail($userId);
            $this->name          = (string) ($u->name ?? '');
            $this->email         = (string) ($u->email ?? '');
            $this->role          = $u->role ?? null; // keep as-is
            $this->department_id = $u->department_id;
            $this->course_id     = $u->course_id;
        }
    }

    /** Livewire v3: fired on any prop change */
    public function updated(string $name, $value): void
    {
        // Normalize “Chairperson” to constant (in case your data or UI still uses it)
        if ($name === 'role' && $value === 'Chairperson') {
            $this->role = User::ROLE_HEAD; // map to "Head"
        }

        if ($name === 'role') {
            $this->department_id = null;
            $this->course_id = null;
        }

        if ($name === 'department_id') {
            $this->course_id = null;
        }
    }

    public function save(): void
    {
        // Allow only these roles to be created via Registrar UI
        $allowedRoles = [
            User::ROLE_DEAN,
            User::ROLE_HEAD,
            User::ROLE_FACULTY,
            // 'Chairperson' // optional alias if you still allow it from UI
        ];

        $this->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255', Rule::unique('users','email')->ignore($this->userId)],
            'role'     => ['required', Rule::in($allowedRoles)],
            'password' => [$this->userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],

            // Dean/Head/Faculty require department
            'department_id' => [
                Rule::requiredIf(in_array($this->role, [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY], true)),
                'nullable','integer', Rule::exists('departments','id'),
            ],

            // Head/Faculty require course (must be under chosen department)
            'course_id' => [
                Rule::requiredIf(in_array($this->role, [User::ROLE_HEAD, User::ROLE_FACULTY], true)),
                'nullable','integer',
                Rule::exists('courses','id')->where(fn($q) => $q->where('department_id', $this->department_id)),
            ],
        ]);

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
        ];

        if (filled($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        // Persist org fields based on role
        if ($this->role === User::ROLE_DEAN) {
            $data['department_id'] = $this->department_id;
            $data['course_id'] = null;
        } elseif (in_array($this->role, [User::ROLE_HEAD, User::ROLE_FACULTY], true)) {
            $data['department_id'] = $this->department_id;
            $data['course_id'] = $this->course_id;
        } else {
            $data['department_id'] = null;
            $data['course_id'] = null;
        }

        User::updateOrCreate(['id' => $this->userId], $data);

        session()->flash('ok', 'User created/updated successfully.');
        $this->redirectRoute('registrar.faculty.index', navigate: true);
    }

    public function render()
    {
        // visibility flags
        $isDean  = ($this->role === User::ROLE_DEAN);
        $isHead  = ($this->role === User::ROLE_HEAD) || ($this->role === 'Chairperson'); // accept alias
        $isFac   = ($this->role === User::ROLE_FACULTY);

        $showDepartment = $isDean || $isHead || $isFac;
        $showCourse     = $isHead || $isFac;

        $departments = Department::select(['id','department_name as name'])
            ->orderBy('department_name')
            ->get();

        $courses = collect();
        if ($showCourse && $this->department_id) {
            $courses = Course::select(['id','course_name as name'])
                ->where('department_id', $this->department_id)
                ->orderBy('course_name')
                ->get();
        }

        // Build role options (include Chairperson if you still show it in UI)
        $roleOptions = [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY];
        // $roleOptions[] = 'Chairperson'; // uncomment if needed as selectable label

        return view('livewire.registrar.faculty.create', [
            'roles'          => $roleOptions,
            'departments'    => $departments,
            'courses'        => $courses,
            'showDepartment' => $showDepartment,
            'showCourse'     => $showCourse,
        ]);
    }
}
