<?php

namespace App\Livewire\Registrar\Faculty;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use App\Models\Course;

// Optional related models used for checks
use App\Models\SectionMeeting;

#[Title('Faculty & Users')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    // URL-synced state (v3)
    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(except: null)]
    public ?string $role = null; // Registrar/Dean/Head/Faculty

    #[Url(except: null)]
    public ?int $departmentId = null;

    #[Url(except: null)]
    public ?int $courseId = null;

    /** Delete UI state */
    public ?int $confirmingDeleteId = null;
    public string $confirmName = '';

    /**
     * Reset pagination when any filter changes
     */
    public function updating($name, $value)
    {
        if (in_array($name, ['search', 'role', 'departmentId', 'courseId'])) {
            $this->resetPage();
        }
    }

    /** Open confirm modal */
    public function confirmDelete(int $userId): void
    {
        $this->confirmingDeleteId = $userId;
        $this->confirmName = '';
    }

    /** Perform delete with FK-safe checks/cleanup */
    public function deleteUser(): void
    {
        if (!$this->confirmingDeleteId) {
            session()->flash('error', 'No user selected.');
            return;
        }

        $user = User::with(['employment', 'userDepartment'])->find($this->confirmingDeleteId);

        if (!$user) {
            session()->flash('error', 'User not found.');
            return;
        }

        // Case-insensitive confirmation by exact name OR email
        $typed = mb_strtolower(trim($this->confirmName));
        $ok = $typed === mb_strtolower((string) $user->name)
            || $typed === mb_strtolower((string) $user->email);

        if (!$ok) {
            session()->flash('error', 'Confirmation mismatch. Type the exact name or email to proceed.');
            return;
        }

        // HARD GUARD: if the faculty is referenced in schedules, block deletion (reassign/cancel first)
        $hasMeetings = class_exists(SectionMeeting::class)
            ? SectionMeeting::where('faculty_id', $user->id)->exists()
            : false;

        if ($hasMeetings) {
            session()->flash('error', 'Cannot delete: user is assigned to one or more Section Meetings. Reassign/cancel those first.');
            return;
        }

        try {
            DB::transaction(function () use ($user) {
                // Clean related rows to avoid FK errors (adjust table names to your schema if different)

                $schema = DB::getSchemaBuilder();

                $maybeDelete = function (string $table) use ($user, $schema) {
                    if ($schema->hasTable($table)) {
                        DB::table($table)->where('user_id', $user->id)->delete();
                    }
                };

                // Common children
                $maybeDelete('faculty_availabilities');
                $maybeDelete('time_slots');
                $maybeDelete('users_employment');
                $maybeDelete('users_department');
                $maybeDelete('faculty_loads');
                $maybeDelete('administrative_loads');

                // Pivots
                if ($schema->hasTable('user_specializations')) {
                    DB::table('user_specializations')->where('user_id', $user->id)->delete();
                }

                // Finally delete the user
                $user->delete();
            });

            $this->confirmingDeleteId = null;
            $this->confirmName = '';

            // If current page becomes empty after deletion, go back a page
            $pageItems = $this->getUsersQuery()->paginate(12);
            if ($this->page > 1 && $pageItems->isEmpty()) {
                $this->previousPage();
            }

            session()->flash('success', 'User deleted successfully.');
        } catch (\Throwable $e) {
            // MySQL FK constraint = SQLSTATE[23000]
            if ((string)$e->getCode() === '23000') {
                session()->flash('error', 'Delete blocked by foreign key constraints. Make sure this user has no linked records (schedules, loads, etc.).');
            } else {
                session()->flash('error', 'Delete failed: '.$e->getMessage());
            }
        }
    }

    /** Cancel delete modal */
    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
        $this->confirmName = '';
    }

    /** Re-usable query builder for pagination refresh */
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

    public function render()
    {
        $q = $this->getUsersQuery();

        // Roles list (uses constants if defined on User)
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
