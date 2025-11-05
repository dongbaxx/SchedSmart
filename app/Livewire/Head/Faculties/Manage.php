<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\{
    User,
    Department,
    UsersDepartment,
    UsersEmployment,
    FacultyAvailability,
    TimeSlot
};

#[Title('Manage Faculty Attributes')]
#[Layout('layouts.head-shell')]
class Manage extends Component
{
    public User $user; // the faculty being managed

    // users_deparment
    public ?string $user_code_id = null;
    public ?string $position = null;        // e.g., 'Faculty'
    public ?int $dept_department_id = null; // LOCKED read-only in UI (same department)

    // users_employments
    public ?string $employment_classification = null; // Teaching
    public ?string $employment_status = null;         // Full-Time | Part-Time
    public ?int $regular_load = null;
    public ?int $extra_load = null;

    public bool $readOnlyOrg = true; // lock department/course editing

    /** Part-time availability editor (Mon–Fri only, custom times per day) */
    public array $days = ['MON','TUE','WED','THU','FRI'];

    /** Day toggles: e.g., ['MON'=>true, ...] */
    public array $dayEnabled = [];

    /** Start/End per day: e.g., ['MON'=>'17:30', ...] */
    public array $dayStart = [];
    public array $dayEnd   = [];

    public function getIsPartTimeProperty(): bool
    {
        return $this->employment_status === 'Part-Time';
    }

    public function mount(User $user)
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();
        abort_unless(in_array($me?->role, ['Head','Chairperson'], true), 403);

        // scope protection: the target must be a Faculty and in my course
        abort_unless($user->role === 'Faculty', 403);
        abort_unless($me->course_id && $user->course_id === $me->course_id, 403);

        // preload
        $this->user = $user->load(['department','course','employment','userDepartment']);

        $this->user_code_id = $this->user->userDepartment->user_code_id ?? null;
        $this->position     = $this->user->userDepartment->position ?? 'Faculty';
        $this->dept_department_id = $this->user->department_id;

        $this->employment_classification = $this->user->employment->employment_classification ?? 'Teaching';
        $this->employment_status         = $this->user->employment->employment_status ?? 'Full-Time';
        $this->regular_load              = $this->user->employment->regular_load ?? 21;
        $this->extra_load                = $this->user->employment->extra_load ?? 6;

        // Initialize defaults for Part-Time editor
        foreach ($this->days as $d) {
            $this->dayEnabled[$d] = false;
            // default to evening window; head can change
            $this->dayStart[$d]   = '17:30';
            $this->dayEnd[$d]     = '20:00';
        }

        // Hydrate existing availability (Mon–Fri only)
        $records = FacultyAvailability::query()
            ->with('timeSlot:id,start_time,end_time')
            ->where('user_id', $this->user->id)
            ->whereIn('day', $this->days)
            ->get(['day','time_slot_id','is_available']);

        foreach ($records as $rec) {
            if (!$rec->timeSlot) continue;
            $d = $rec->day;
            $this->dayEnabled[$d] = (bool)$rec->is_available;
            // store hh:mm
            $this->dayStart[$d] = substr($rec->timeSlot->start_time, 0, 5);
            $this->dayEnd[$d]   = substr($rec->timeSlot->end_time, 0, 5);
        }
    }

    /**
     * Livewire v3 hook: when status changes, show/hide the column instantly.
     */
    public function updatedEmploymentStatus(): void
    {
        // no-op needed; but keep method so the UI re-renders live when status flips
    }

    /*** Department save ***/
    public function saveDepartment()
    {
        $this->validate([
            'user_code_id'       => ['nullable','string','max:255'],
            'position'           => ['nullable','string','max:255'],
        ]);

        $record = $this->user->userDepartment()->first() ?: new UsersDepartment(['user_id' => $this->user->id]);
        $record->user_code_id  = $this->user_code_id;
        $record->position      = $this->position ?: 'Faculty';
        $record->department_id = $this->user->department_id; // locked to user’s dept
        $record->save();

        session()->flash('success_department', 'Department record updated.');
    }

    /*** Employment save ***/
    public function saveEmployment()
    {
        $this->validate([
            'employment_classification' => ['required','string','max:255'],
            'employment_status'         => ['required','string', Rule::in(['Full-Time','Part-Time'])],
            'regular_load'              => ['nullable','integer','min:0','max:45'],
            'extra_load'                => ['nullable','integer','min:0','max:24'],
        ]);

        $emp = $this->user->employment()->first() ?: new UsersEmployment(['user_id' => $this->user->id]);
        $emp->employment_classification = $this->employment_classification;
        $emp->employment_status         = $this->employment_status;
        $emp->regular_load              = $this->regular_load ?? 0;
        $emp->extra_load                = $this->extra_load ?? 0;
        $emp->save();

        session()->flash('success_employment', 'Employment details updated.');
    }

    /*** Save: Availability (Part-Time only) ***/
    public function saveAvailability()
    {
        if (!$this->isPartTime) {
            session()->flash('success_availability', 'Availability input applies to Part-Time faculty only.');
            return;
        }

        // Build dynamic rules: only validate days that are enabled
        $rules = [];
        foreach ($this->days as $d) {
            if (!empty($this->dayEnabled[$d])) {
                $rules["dayStart.$d"] = ['required','date_format:H:i'];
                $rules["dayEnd.$d"]   = ['required','date_format:H:i','after:dayStart.'.$d];
            }
        }

        // If at least one day is enabled, validate; else allow clearing all
        if (!empty($rules)) {
            $this->validate($rules, [], [
                "dayStart.$d" => 'start time',
                "dayEnd.$d"   => 'end time',
            ]);
        }

        // For enabled days: ensure time slot, upsert availability (is_available=1)
        // For disabled days: remove any availability rows for that day
        foreach ($this->days as $d) {
            if (!empty($this->dayEnabled[$d])) {
                $start = $this->normalizeToSec($this->dayStart[$d] ?? null); // 'HH:MM:00'
                $end   = $this->normalizeToSec($this->dayEnd[$d]   ?? null);

                if (!$start || !$end) continue;

                $slotId = $this->ensureTimeSlot($start, $end);

                FacultyAvailability::updateOrCreate(
                    [
                        'user_id'      => $this->user->id,
                        'day'          => $d,
                        'time_slot_id' => $slotId,
                    ],
                    [
                        'is_available' => true,
                        'is_preferred' => false,
                    ]
                );

                // Clean up other slots (if any) for the same day to avoid duplicates
                FacultyAvailability::where('user_id', $this->user->id)
                    ->where('day', $d)
                    ->where('time_slot_id', '!=', $slotId)
                    ->delete();
            } else {
                // If disabled, remove any availability for that day
                FacultyAvailability::where('user_id', $this->user->id)
                    ->where('day', $d)
                    ->delete();
            }
        }

        session()->flash('success_availability', 'Part-Time availability saved (custom time per day).');
    }

    private function ensureTimeSlot(string $startTime, string $endTime): int
    {
        // Ensure unique slot by (start_time, end_time)
        $slot = TimeSlot::firstOrCreate(
            ['start_time' => $startTime, 'end_time' => $endTime],
            ['is_active' => true]
        );
        return $slot->id;
    }

    private function normalizeToSec(?string $hhmm): ?string
    {
        if (!$hhmm || !preg_match('/^\d{2}:\d{2}$/', $hhmm)) return null;
        return $hhmm . ':00';
    }

    /** Helper the scheduler can call to restrict Full-Timers to weekdays only */
    public static function allowedDaysForFullTimer(): array
    {
        return ['MON','TUE','WED','THU','FRI']; // no weekends
    }

    public function render()
    {
        return view('livewire.head.faculties.manage', [
            'departments' => Department::orderBy('department_name')->get(['id','department_name']),
            'roleBadge'   => $this->user->role ?? '—',
        ]);
    }
}
