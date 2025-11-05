<?php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Department, UsersDepartment, UsersEmployment};

#[Title('Manage Academic Attributes')]
#[Layout('layouts.dean-shell')]
class Manage extends Component
{
    public User $user;

    // users_deparment
    public ?string $user_code_id = null;
    public ?string $position = null;        // Faculty | Head (no Dean/Registrar here)
    public ?int $dept_department_id = null; // auto-locked to Dean dept, but kept for UI binding

    // users_employments
    public ?string $employment_classification = null; // Teaching/Non-Teaching
    public ?string $employment_status = null;         // Full-Time/Part-Time/Contractual
    public ?int $regular_load = null;
    public ?int $extra_load = null;

    public function mount(User $user)
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        // target user must be in the same department & role is Head/Faculty
        abort_if(
            $user->department_id !== $dean->department_id ||
            !in_array($user->role, [User::ROLE_HEAD, User::ROLE_FACULTY], true),
            403
        );

        $this->user = $user->load(['department','course','employment','userDepartment']);

        // preload users_deparment
        $this->user_code_id       = $this->user->userDepartment->user_code_id ?? null;
        $this->position           = $this->user->userDepartment->position ?? ($this->user->role === User::ROLE_HEAD ? 'Head' : 'Faculty');
        $this->dept_department_id = $dean->department_id; // lock to dean's dept

        // preload employment
        $this->employment_classification = $this->user->employment->employment_classification ?? 'Teaching';
        $this->employment_status         = $this->user->employment->employment_status ?? 'Full-Time';
        $this->regular_load              = $this->user->employment->regular_load ?? 21;
        $this->extra_load                = $this->user->employment->extra_load ?? 6;
    }

    /** Save/update users_deparment (locked to Dean dept) */
    public function saveDepartment()
    {
        $this->validate([
            'user_code_id'        => ['nullable','string','max:255'],
            'position'            => ['required','string','in:Faculty,Head'],
            'dept_department_id'  => ['nullable','integer'], // ignored; we force dean dept
        ]);

        $deanDept = Auth::user()->department_id;

        $record = $this->user->userDepartment()->first() ?? new UsersDepartment(['user_id' => $this->user->id]);
        $record->user_code_id  = $this->user_code_id;
        $record->position      = $this->position; // Faculty/Head only
        $record->department_id = $deanDept;       // hard lock
        $record->save();

        session()->flash('success_department', 'User Department updated.');
    }

    /** Save/update users_employments */
    public function saveEmployment()
    {
        $this->validate([
            'employment_classification' => ['required','string','in:Teaching,Non-Teaching'],
            'employment_status'         => ['required','string','in:Full-Time,Part-Time,Contractual'],
            'regular_load'              => ['nullable','integer','min:0','max:45'],
            'extra_load'                => ['nullable','integer','min:0','max:24'],
        ]);

        $emp = $this->user->employment()->first() ?? new UsersEmployment(['user_id' => $this->user->id]);
        $emp->employment_classification = $this->employment_classification;
        $emp->employment_status         = $this->employment_status;
        $emp->regular_load              = $this->regular_load ?? 0;
        $emp->extra_load                = $this->extra_load ?? 0;
        $emp->save();

        session()->flash('success_employment', 'Employment details updated.');
    }

    public function render()
    {
        // Dean can only see his department in the dropdown (kept for display)
        $departments = Department::where('id', Auth::user()->department_id)->get(['id','department_name']);

        return view('livewire.dean.people.manage', [
            'departments' => $departments,
            'roleBadge'   => $this->user->role ?? '—',
        ]);
    }
}

