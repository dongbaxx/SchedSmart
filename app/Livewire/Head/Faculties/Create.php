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

#[Title('Add / Edit Faculty')]
#[Layout('layouts.head-shell')]
class Create extends Component
{
    public ?int $userId = null;

    public string $name = '';
    public string $email = '';
    public ?string $password = null;
    public string $password_confirmation = '';

    public ?int $myDeptId = null;
    public ?int $myCourseId = null;

    /** edit mode flag */
    public bool $isEdit = false;

    public function mount(?int $userId = null): void
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();
        abort_unless(in_array($me?->role, ['Head','Chairperson'], true), 403);

        // lock department/course from the Head’s own profile
        $this->myDeptId   = $me?->department_id;
        $this->myCourseId = $me?->course_id;

        // >>> SUNOD SA DEAN STYLE: resolve userId from param/route/query
        $routeId = request()->route('userId')
            ?? request()->route('user')
            ?? request()->integer('userId');

        $this->userId = $userId ?? (is_numeric($routeId) ? (int) $routeId : null);

        // kung naay userId, EDIT MODE → retrieve old data
        if ($this->userId) {
            $this->isEdit = true;

            $u = User::query()
                ->where('id', $this->userId)
                // security: dapat either siya mismo, or faculty under iyang dept+course
                ->where(function ($q) use ($me) {
                    $q->where('id', $me->id)
                      ->orWhere(function ($qq) use ($me) {
                          $qq->where('role', 'Faculty')
                             ->where('department_id', $me->department_id)
                             ->where('course_id', $me->course_id);
                      });
                })
                ->firstOrFail();

            // >>> PREFILL only OLD DATA for edit
            $this->name  = (string) ($u->name ?? '');
            $this->email = (string) ($u->email ?? '');

            // password fields stay null/blank (optional change)
            $this->password = null;
            $this->password_confirmation = '';
        }
    }

    public function save(): void
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();
        abort_unless(in_array($me?->role, ['Head','Chairperson'], true), 403);

        // base validation
        $rules = [
            'name'  => ['required','string','max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users','email')->ignore($this->userId),
            ],
        ];

        // password rules depende kung create or edit
        if ($this->isEdit) {
            if ($this->password) {
                $rules['password'] = ['nullable','confirmed', Password::defaults()];
            }
        } else {
            $rules['password'] = ['required','confirmed', Password::defaults()];
        }

        $this->validate($rules);

        if ($this->isEdit && $this->userId) {
            // UPDATE EXISTING USER (name + email + optional password)
            $user = User::findOrFail($this->userId);

            $user->name  = $this->name;
            $user->email = $this->email;

            if ($this->password) {
                $user->password = Hash::make($this->password);
            }

            // NOTE: di nato usbon ang role/department_id/course_id diri
            $user->save();

            session()->flash('ok', 'Faculty information updated successfully.');
        } else {
            // CREATE NEW FACULTY under Head’s dept + course
            User::create([
                'name'          => $this->name,
                'email'         => $this->email,
                'password'      => Hash::make($this->password),
                'role'          => 'Faculty',
                'department_id' => $this->myDeptId,
                'course_id'     => $this->myCourseId,
            ]);

            session()->flash('ok', 'Faculty created successfully.');
        }

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
