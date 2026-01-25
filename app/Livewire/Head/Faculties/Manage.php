<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

use App\Models\User;
use App\Models\UsersDepartment;
use App\Models\UsersEmployment;
use App\Models\FacultyAvailability;
use App\Models\TimeSlot;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

#[Title('Manage Faculty')]
#[Layout('layouts.head-shell')]
class Manage extends Component
{
    // keep user id as scalar to avoid Livewire hydration issues
    public int $userId;
    public ?User $user = null;

    /** ✅ Users Department Record (NEW) */
    public ?string $user_code_id = null;
    public ?string $position = null;          // Faculty | Head | Dean
    public ?int $dept_department_id = null;   // locked to Head department (display only)

    // Employment (users_employments)
    public $employment_classification = 'Teaching';
    public $employment_status = 'Part-Time'; // Full-Time | Part-Time
    public $regular_load = 0;
    public $extra_load = 0;

    // Availability (Part-Time only)
    public array $dayEnabled = [];
    public array $dayStart = [];
    public array $dayEnd = [];

    // UI helpers
    public array $days = ['MON','TUE','WED','THU','FRI'];
    public array $gridStartOptions = [];
    public array $gridEndOptions = [];

    public bool $regularLoadTouched = false;

    public int $DEFAULT_LOAD_HEAD_DEAN = 9;
    public int $DEFAULT_LOAD_FACULTY_FULLTIME = 24;
    public int $DEFAULT_LOAD_FACULTY_PARTTIME = 12;


    public function mount(User $user)
    {
        $this->userId = (int) $user->id;

        // ✅ optional scope (same dept only) — if you want strict head scoping
        // $head = Auth::user();
        // abort_unless($head && $head->role === User::ROLE_HEAD, 403);
        // abort_unless($user->department_id === $head->department_id, 403);

        $this->user = User::with(['department','course','userDepartment'])
            ->findOrFail($this->userId);

        // time options (30-min grid)
        $this->gridStartOptions = $this->makeTimeGrid('07:00', '20:00', 30);
        $this->gridEndOptions   = $this->makeTimeGrid('07:30', '20:30', 30);

        // defaults
        foreach ($this->days as $d) {
            $this->dayEnabled[$d] = false;
            $this->dayStart[$d]   = '07:30';
            $this->dayEnd[$d]     = '12:00';
        }

        /** ✅ Users Department preload (NEW) */
        $head = Auth::user();
        $this->dept_department_id = $head?->department_id ?? $this->user->department_id;

        $ud = UsersDepartment::where('user_id', $this->userId)->first();
        $this->user_code_id = $ud?->user_code_id;

        if ($ud?->position) {
            $this->position = $ud->position;
        } else {
            // fallback based on role
            if ($this->user->role === User::ROLE_HEAD) $this->position = 'Head';
            elseif ($this->user->role === User::ROLE_DEAN) $this->position = 'Dean';
            else $this->position = 'Faculty';
        }

        // load employment row directly from UsersEmployment model
        $emp = UsersEmployment::where('user_id', $this->userId)->first();
        if ($emp) {
            $this->employment_classification = (string) $emp->employment_classification;
            $this->employment_status         = (string) $emp->employment_status;
            $this->regular_load              = (int) ($emp->regular_load ?? 0);
            $this->extra_load                = (int) ($emp->extra_load ?? 0);

            $this->regularLoadTouched = true; // ✅ because it already exists (considered user-defined)
        } else {
            // ✅ first time auto default (editable)
            $this->regular_load = $this->defaultRegularLoad();
            $this->extra_load = 0;

            $this->regularLoadTouched = false;
        }


        // load availability into UI
        $this->loadExistingAvailability();
    }

    public function getIsPartTimeProperty(): bool
    {
        return $this->employment_status === 'Part-Time';
    }

    /** ✅ Save Users Department Record (NEW) */
    public function saveDepartmentRecord()
    {
        $this->validate([
            'user_code_id' => ['nullable','string','max:255'],
            'position'     => ['nullable','string', Rule::in(['Faculty','Head','Dean'])],
        ]);

        $head = Auth::user();
        $headDeptId = (int) ($head?->department_id ?? $this->user->department_id);

        DB::transaction(function () use ($headDeptId) {
            $ud = UsersDepartment::firstOrNew(['user_id' => $this->userId]);

            $ud->user_id        = $this->userId;
            $ud->user_code_id   = $this->user_code_id;
            $ud->position       = $this->position ?: 'Faculty';
            $ud->department_id  = $headDeptId; // ✅ locked to Head dept
            $ud->save();
        });

        // refresh user display
        $this->user = User::with(['department','course','userDepartment'])
            ->findOrFail($this->userId);

        session()->flash('success_department', 'Users Department record updated.');
    }

    /** Save Employment (Full-Time will auto-create timeslot + availabilities on SAVE) */
    public function saveEmployment()
    {
        $this->validate([
            'employment_classification' => ['required','string','max:255'],
            'employment_status'         => ['required','string', Rule::in(['Full-Time','Part-Time'])],
            'regular_load'              => ['nullable','integer','min:0','max:45'],
            'extra_load'                => ['nullable','integer','min:0','max:24'],
        ]);
        $this->regular_load = ($this->regular_load === '' || $this->regular_load === null) ? 0 : (int) $this->regular_load;
        $this->extra_load   = ($this->extra_load   === '' || $this->extra_load   === null) ? 0 : (int) $this->extra_load;
        DB::transaction(function () {

            // ✅ Create/update employment row safely
            $emp = UsersEmployment::firstOrNew(['user_id' => $this->userId]);

            $emp->employment_classification = $this->employment_classification;
            $emp->employment_status         = $this->employment_status;
            $emp->regular_load              = $this->regular_load ?? 0;
            $emp->extra_load                = $this->extra_load ?? 0;
            $emp->user_id                   = $this->userId; // force fill
            $emp->save();

            // ✅ AUTO INSERT if Full-Time (on SAVE)
            if ($this->employment_status === 'Full-Time') {
                $this->applyFullTimeDefaults($this->userId);
            }
        });

        // refresh display user
        $this->user = User::with(['department','course','userDepartment'])
            ->findOrFail($this->userId);

        session()->flash('success_employment', 'Employment details updated.');

        if ($this->employment_status === 'Full-Time') {
            session()->flash('success_availability', 'Auto-saved: MON–FRI, 07:30–18:00 (Full-Time).');
        }
    }

    /** Save Availability (Part-Time only) */
    public function saveAvailability()
    {
        if (!$this->isPartTime) {
            return;
        }

        // Validate enabled days
        foreach ($this->days as $day) {
            if (!($this->dayEnabled[$day] ?? false)) continue;

            $this->validate([
                "dayStart.$day" => ['required','date_format:H:i'],
                "dayEnd.$day"   => ['required','date_format:H:i'],
            ]);

            $start = $this->dayStart[$day];
            $end   = $this->dayEnd[$day];

            if (strtotime($start) >= strtotime($end)) {
                $this->addError("dayEnd.$day", 'End time must be later than start time.');
                return;
            }

        }

        DB::transaction(function () {

            foreach ($this->days as $day) {
                $enabled = (bool) ($this->dayEnabled[$day] ?? false);

                if (!$enabled) {
                    FacultyAvailability::where('user_id', $this->userId)
                        ->where('day', $day)
                        ->delete();
                    continue;
                }

                $slotId = $this->ensureTimeSlot(
                    $this->dayStart[$day] . ':00',
                    $this->dayEnd[$day] . ':00'
                );

                FacultyAvailability::updateOrCreate(
                    ['user_id' => $this->userId, 'day' => $day],
                    ['time_slot_id' => $slotId, 'is_available' => true, 'is_preferred' => false]
                );
            }
        });
        $this->loadExistingAvailability();
        session()->flash('success_availability', 'Availability updated.');
    }

    /** ✅ Full-Time Defaults: MON–FRI + 07:30–18:00 (auto-insert) */
    private function applyFullTimeDefaults(int $userId): void
    {
        $days = ['MON','TUE','WED','THU','FRI'];

        $slotId = $this->ensureTimeSlot('07:30:00', '18:00:00');

        foreach ($days as $d) {
            FacultyAvailability::updateOrCreate(
                ['user_id' => $userId, 'day' => $d],
                ['time_slot_id' => $slotId, 'is_available' => true, 'is_preferred' => false]
            );

            // remove duplicates for same day
            FacultyAvailability::where('user_id', $userId)
                ->where('day', $d)
                ->where('time_slot_id', '!=', $slotId)
                ->delete();
        }

        // update UI state (even if hidden)
        foreach ($days as $d) {
            $this->dayEnabled[$d] = true;
            $this->dayStart[$d]   = '07:30';
            $this->dayEnd[$d]     = '18:00';
        }
    }

    /** Ensure a timeslot exists and return its id */
    private function ensureTimeSlot(string $startTime, string $endTime): int
    {
        $slot = TimeSlot::firstOrCreate(
            ['start_time' => $startTime, 'end_time' => $endTime],
            ['is_active' => true]
        );

        if (isset($slot->is_active) && !$slot->is_active) {
            $slot->is_active = true;
            $slot->save();
        }

        return (int) $slot->id;
    }

    /** Load existing availability rows into the UI */
    private function loadExistingAvailability(): void
    {
        $rows = FacultyAvailability::with('timeSlot')
            ->where('user_id', $this->userId)
            ->whereIn('day', $this->days)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('day');

        foreach ($this->days as $day) {
            if (!isset($rows[$day])) {
                $this->dayEnabled[$day] = false;
                continue;
            }

            $this->dayEnabled[$day] = true;

            $ts = $rows[$day]->timeSlot;
            if ($ts) {
                $startRaw = (string) $ts->getRawOriginal('start_time'); // ✅ raw from DB
                $endRaw   = (string) $ts->getRawOriginal('end_time');   // ✅ raw from DB

                $this->dayStart[$day] = substr($startRaw, 0, 5); // "17:00"
                $this->dayEnd[$day]   = substr($endRaw, 0, 5);   // "20:00"
            }

        }
    }

    /** Build a 30-minute time grid list */
    private function makeTimeGrid(string $start, string $end, int $stepMinutes): array
    {
        $out = [];
        $cur = strtotime($start);
        $to  = strtotime($end);

        while ($cur <= $to) {
            $out[] = date('H:i', $cur);
            $cur = strtotime("+{$stepMinutes} minutes", $cur);
        }

        return $out;
    }

    private function defaultRegularLoad(): int
    {
        // Head & Dean always 9
        if (in_array($this->user?->role, [User::ROLE_HEAD, User::ROLE_DEAN], true)) {
            return $this->DEFAULT_LOAD_HEAD_DEAN;
        }

        // Faculty depends on status
        return ($this->employment_status === 'Full-Time')
            ? $this->DEFAULT_LOAD_FACULTY_FULLTIME
            : $this->DEFAULT_LOAD_FACULTY_PARTTIME;
    }

    public function updatedRegularLoad()
    {
        $this->regularLoadTouched = true;
    }

    public function updatedEmploymentStatus($value)
    {
        $this->employment_status = $value;

        // ✅ ALWAYS auto-change regular_load based on status/role
        // If Head/Dean must still always be 9, keep this block:
        if (in_array($this->user?->role, [User::ROLE_HEAD, User::ROLE_DEAN], true)) {
            $this->regular_load = 9;
            return;
        }

        // Faculty: Full-Time = 24, Part-Time = 13
        $this->regular_load = ($value === 'Full-Time') ? 24 : 12;
    }

    public function render()
    {
        return view('livewire.head.faculties.manage', [
            'user'             => $this->user,
            'days'             => $this->days,
            'gridStartOptions' => $this->gridStartOptions,
            'gridEndOptions'   => $this->gridEndOptions,
        ]);
    }
}
