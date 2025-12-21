<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

use App\Models\User;
use App\Models\UsersEmployment;
use App\Models\FacultyAvailability;
use App\Models\TimeSlot;

use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

#[Title('Manage Faculty')]
#[Layout('layouts.head-shell')]
class Manage extends Component
{
    // keep user id as scalar to avoid Livewire hydration issues
    public int $userId;
    public ?User $user = null;

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

    public function mount(User $user)
    {
        $this->userId = (int) $user->id;

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

        // load employment row directly from UsersEmployment model
        $emp = UsersEmployment::where('user_id', $this->userId)->first();
        if ($emp) {
            $this->employment_classification = (string) $emp->employment_classification;
            $this->employment_status         = (string) $emp->employment_status;
            $this->regular_load              = (int) ($emp->regular_load ?? 0);
            $this->extra_load                = (int) ($emp->extra_load ?? 0);
        }

        // load availability into UI
        $this->loadExistingAvailability();
    }

    public function getIsPartTimeProperty(): bool
    {
        return $this->employment_status === 'Part-Time';
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

            // Friday strict ranges
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
            }
        });

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

        // if you have is_active column, keep it active
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
