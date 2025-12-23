<?php

namespace App\Livewire\Dean\People;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

use App\Models\{
    User,
    Department,
    UsersDepartment,
    UsersEmployment,
    FacultyAvailability,
    TimeSlot
};

#[Title('Manage Academic Attributes')]
#[Layout('layouts.dean-shell')]
class Manage extends Component
{
    // Use scalar id to avoid hydration issues (same pattern as Head)
    public int $userId;
    public ?User $user = null;

    /** Users Department record */
    public ?string $user_code_id = null;
    public ?string $position = null; // Faculty | Head | Dean
    public ?int $dept_department_id = null; // locked to Dean dept (display only)

    /** Employment */
    public string $employment_classification = 'Teaching';
    public string $employment_status = 'Full-Time'; // Full-Time | Part-Time | Contractual
    public int $regular_load = 0;
    public int $extra_load   = 0;

    /** Availability (Part-Time only) */
    public array $days = ['MON','TUE','WED','THU','FRI'];
    public array $dayEnabled = [];
    public array $dayStart   = [];
    public array $dayEnd     = [];

    /** Time grid options (30-min grid) — same as Head take note */
    public array $gridStartOptions = [];
    public array $gridEndOptions   = [];

    public function mount(User $user)
    {
        /** @var \App\Models\User|null $dean */
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        $isSelf   = $user->id === $dean->id;
        $sameDept = $user->department_id === $dean->department_id;

        $allowedRoles = [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY];

        abort_unless(
            $isSelf || ($sameDept && in_array($user->role, $allowedRoles, true)),
            403
        );

        $this->userId = (int) $user->id;

        $this->user = User::with(['department','course','employment','userDepartment'])
            ->findOrFail($this->userId);

        // grid (30 mins)
        $this->gridStartOptions = $this->makeTimeGrid('07:00', '20:00', 30);
        $this->gridEndOptions   = $this->makeTimeGrid('07:30', '20:30', 30);

        // Defaults for availability UI
        foreach ($this->days as $d) {
            $this->dayEnabled[$d] = false;
            $this->dayStart[$d]   = '07:30';
            $this->dayEnd[$d]     = '12:00';
        }

        // UsersDepartment preload (or infer)
        $this->user_code_id = $this->user->userDepartment->user_code_id ?? null;

        if ($this->user->userDepartment?->position) {
            $this->position = $this->user->userDepartment->position;
        } else {
            if ($this->user->role === User::ROLE_DEAN) $this->position = 'Dean';
            elseif ($this->user->role === User::ROLE_HEAD) $this->position = 'Head';
            else $this->position = 'Faculty';
        }

        // locked to Dean dept
        $this->dept_department_id = (int) $dean->department_id;

        // Employment preload
        $emp = UsersEmployment::where('user_id', $this->userId)->first();
        if ($emp) {
            $this->employment_classification = (string) ($emp->employment_classification ?? 'Teaching');
            $this->employment_status         = (string) ($emp->employment_status ?? 'Full-Time');
            $this->regular_load              = (int) ($emp->regular_load ?? 0);
            $this->extra_load                = (int) ($emp->extra_load ?? 0);
        } else {
            // keep defaults but you can set dean defaults here if you want
            $this->employment_classification = 'Teaching';
            $this->employment_status         = 'Full-Time';
            $this->regular_load              = 21;
            $this->extra_load                = 6;
        }

        // Hydrate existing availability to UI
        $this->loadExistingAvailability();
    }

    public function getIsPartTimeProperty(): bool
    {
        return $this->employment_status === 'Part-Time';
    }

    /** Save Users Department record (Dean dept locked) */
    public function saveDepartment()
    {
        $this->validate([
            'user_code_id' => ['nullable','string','max:255'],
            'position'     => ['nullable','string', Rule::in(['Faculty','Head','Dean'])],
        ]);

        $dean = Auth::user();
        $deanDeptId = (int) $dean->department_id;

        DB::transaction(function () use ($deanDeptId) {
            $record = UsersDepartment::firstOrNew(['user_id' => $this->userId]);

            $record->user_id        = $this->userId;
            $record->user_code_id   = $this->user_code_id;
            $record->position       = $this->position ?: 'Faculty';
            $record->department_id  = $deanDeptId; // locked
            $record->save();
        });

        $this->refreshUser();
        session()->flash('success_department', 'Department record updated.');
    }

    /** Save Employment (Full-Time will auto-create timeslot + availabilities on SAVE) */
    public function saveEmployment()
    {
        $this->validate([
            'employment_classification' => ['required','string','max:255'],
            'employment_status'         => ['required','string', Rule::in(['Full-Time','Part-Time','Contractual'])],
            'regular_load'              => ['nullable','integer','min:0','max:45'],
            'extra_load'                => ['nullable','integer','min:0','max:24'],
        ]);

        DB::transaction(function () {
            $emp = UsersEmployment::firstOrNew(['user_id' => $this->userId]);

            $emp->user_id                   = $this->userId;
            $emp->employment_classification = $this->employment_classification;
            $emp->employment_status         = $this->employment_status;
            $emp->regular_load              = $this->regular_load ?? 0;
            $emp->extra_load                = $this->extra_load ?? 0;
            $emp->save();

            // ✅ SAME as Head: Full-Time auto insert on save
            if ($this->employment_status === 'Full-Time') {
                $this->applyFullTimeDefaults($this->userId);
            }
        });

        $this->refreshUser();
        session()->flash('success_employment', 'Employment details updated.');

        if ($this->employment_status === 'Full-Time') {
            session()->flash('success_availability', 'Auto-saved: MON–FRI, 07:30–18:00 (Full-Time).');
        }
    }

    /** Save Availability (Part-Time only) */
    public function saveAvailability()
    {
        if (!$this->isPartTime) {
            // Full-time is auto; no manual saving
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

            // Friday strict ranges (same as Head take note)
            if ($day === 'FRI') {
                $allowed = [
                    ['13:00','16:00'],
                    ['14:30','17:30'],
                    ['16:00','19:00'],
                    ['17:30','20:30'],
                ];
                $ok = false;
                foreach ($allowed as [$a,$b]) {
                    if ($start === $a && $end === $b) { $ok = true; break; }
                }
                if (!$ok) {
                    $this->addError("dayEnd.$day", 'Friday must match allowed time ranges.');
                    return;
                }
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

                // remove duplicates for same day
                FacultyAvailability::where('user_id', $this->userId)
                    ->where('day', $day)
                    ->where('time_slot_id', '!=', $slotId)
                    ->delete();
            }
        });

        session()->flash('success_availability', 'Availability updated.');
        $this->loadExistingAvailability(); // refresh UI state
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

    /** Ensure a timeslot exists and return its id (keep is_active=1) */
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
                $this->dayStart[$day] = substr((string) $ts->start_time, 0, 5);
                $this->dayEnd[$day]   = substr((string) $ts->end_time, 0, 5);
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

    private function refreshUser(): void
    {
        $this->user = User::with(['department','course','employment','userDepartment'])
            ->findOrFail($this->userId);
    }

    public function render()
    {
        $dean = Auth::user();

        return view('livewire.dean.people.manage', [
            'user'             => $this->user,
            'days'             => $this->days,
            'gridStartOptions' => $this->gridStartOptions,
            'gridEndOptions'   => $this->gridEndOptions,

            // display-only (locked)
            'departments' => Department::where('id', $dean->department_id)
                ->get(['id','department_name']),
            'roleBadge' => $this->user?->role ?? '—',
        ]);
    }
}
