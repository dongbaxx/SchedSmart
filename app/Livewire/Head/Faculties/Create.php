<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use App\Models\Department;
use App\Models\Course;

#[Title('Add Faculty')]
#[Layout('layouts.head-shell')]
class Create extends Component
{
    public string $name = '';
    public string $email = '';
    public ?string $password = null;
    public string $password_confirmation = '';

    public ?int $myDeptId = null;
    public ?int $myCourseId = null;

    public function mount(): void
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();
        abort_unless(in_array($me?->role, ['Head','Chairperson'], true), 403);

        // lock department/course from the Head’s own profile
        $this->myDeptId   = $me?->department_id;
        $this->myCourseId = $me?->course_id;
    }

    public function save(): void
    {
        $this->validate([
            'name'     => ['required','string','max:255'],
            'email'    => ['required','email','max:255', Rule::unique('users','email')],
            'password' => ['required','confirmed', Password::defaults()],
        ]);

        // Heads can only create FACULTY in THEIR department/course
        User::create([
            'name'          => $this->name,
            'email'         => $this->email,
            'password'      => Hash::make($this->password),
            'role'          => 'Faculty',
            'department_id' => $this->myDeptId,
            'course_id'     => $this->myCourseId,
        ]);

        session()->flash('ok', 'Faculty created successfully.');
        $this->redirectRoute('head.faculties.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.head.faculties.create', [
            'dept'   => $this->myDeptId ? Department::find($this->myDeptId) : null,
            'course' => $this->myCourseId ? Course::find($this->myCourseId) : null,
        ]);
    }
}
