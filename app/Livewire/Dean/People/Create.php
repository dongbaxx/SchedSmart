<?php

// app/Livewire/Dean/People/Create.php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Course};

#[Title('Add/Edit Head or Faculty')]
#[Layout('layouts.dean-shell')]
class Create extends Component
{
    public ?int $userId = null;

    public string $name = '';
    public string $email = '';
    public ?string $role = null; // Head|Faculty
    public ?string $password = null;
    public string $password_confirmation = '';

    public ?int $course_id = null;

    public function mount(?int $userId = null): void
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        $this->userId = $userId;

        if ($userId) {
            $u = User::findOrFail($userId);

            // cannot edit outside my department or outside allowed roles
            abort_if(
                $u->department_id !== $dean->department_id
                || !in_array($u->role, [User::ROLE_HEAD, User::ROLE_FACULTY], true),
                403
            );

            $this->name  = (string) $u->name;
            $this->email = (string) $u->email;
            $this->role  = (string) $u->role;
            $this->course_id = $u->course_id;
        }
    }

    public function updated(string $name, $value): void
    {
        if ($name === 'role') {
            // no department select here; dean's department is enforced in save()
        }
    }

    public function save(): void
    {
        $dean = Auth::user();
        $deptId = (int) $dean->department_id;

        $allowedRoles = [User::ROLE_HEAD, User::ROLE_FACULTY];

        $this->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255', Rule::unique('users','email')->ignore($this->userId)],
            'role'     => ['required', Rule::in($allowedRoles)],
            'password' => [$this->userId ? 'nullable' : 'required', 'confirmed', Password::defaults()],

            // Head/Faculty must have course_id under the Dean’s department
            'course_id'=> [
                'required','integer',
                Rule::exists('courses','id')->where(fn($q) => $q->where('department_id', $deptId)),
            ],
        ]);

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
            'department_id' => $deptId, // lock to dean's department
            'course_id'     => $this->course_id,
        ];

        if (filled($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        // prevent upgrading to Dean/Registrar via tampering
        abort_if(!in_array($data['role'], $allowedRoles, true), 403);

        $user = User::updateOrCreate(['id' => $this->userId], $data);

        // double-check confinement after save (in case of race/changes)
        abort_if($user->department_id !== $deptId, 403);

        session()->flash('ok', 'User created/updated successfully.');
        $this->redirectRoute('dean.people.index', navigate: true);
    }

    public function render()
    {
        $deptId = (int) Auth::user()->department_id;

        $courses = Course::select(['id','course_name as name'])
            ->where('department_id', $deptId)
            ->orderBy('course_name')
            ->get();

        return view('livewire.dean.people.create', [
            'roles'   => [User::ROLE_HEAD, User::ROLE_FACULTY],
            'courses' => $courses,
        ]);
    }
}

