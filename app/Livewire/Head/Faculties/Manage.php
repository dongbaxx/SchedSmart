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

    /** ── GRID WINDOWS USED BY THE SCHEDULER (Editor::$slots) ─────────────── */
    private array $gridStarts = ['07:30','09:00','10:30','13:00','14:30','16:00','17:30','19:00'];
    private array $gridEnds   = ['09:00','10:30','12:00','14:30','16:00','17:30','19:00','20:30'];

    /** valid 3h windows (two consecutive 1.5h blocks) — used for FRI rule */
    private array $gridWindows3h = [
        ['13:00','16:00'],
        ['14:30','17:30'],
        ['16:00','19:00'],
        ['17:30','20:30'],
    ];

    /** PT hard end limit (mirrors Editor::$PT_END_LIMIT) */
    private string $PT_END_LIMIT = '21:00:00';

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

    /** Livewire v3 hook: when status changes, show/hide the column instantly. */
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

    /*** Save: Availability (Part-Time only) — SNAP TO GRID ***/
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
            $this->validate($rules);
        }

        // Process each day
        foreach ($this->days as $d) {
            if (!empty($this->dayEnabled[$d])) {

                // raw inputs (HH:MM)
                $rawStart = $this->dayStart[$d] ?? null;
                $rawEnd   = $this->dayEnd[$d]   ?? null;
                if (!$rawStart || !$rawEnd) continue;

                // snap to the nearest grid slot boundaries
                $snappedStart = $this->snapStart($rawStart);  // e.g., 17:22 -> 17:30
                $snappedEnd   = $this->snapEnd($rawEnd);      // e.g., 20:35 -> 20:30

                // ensure start < end after snap
                if (!$this->isValidRange($snappedStart, $snappedEnd)) {
                    $this->addError("dayEnd.$d", 'The time range does not align to schedulable slots.');
                    return;
                }

                // PT end cap (safety; Editor grid already ends 20:30)
                $startSec = $this->normalizeToSec($snappedStart);
                $endSec   = $this->normalizeToSec($snappedEnd);
                if ($endSec > $this->PT_END_LIMIT) { $endSec = $this->PT_END_LIMIT; }

                // Friday ≥ 3 hours (prefer an exact 3h grid window that fits inside the raw input)
                if ($d === 'FRI') {
                    $rawS = $this->normalizeToSec($rawStart);
                    $rawE = $this->normalizeToSec($rawEnd);

                    $forced = false;
                    foreach ($this->gridWindows3h as [$gs, $ge]) {
                        $gsSec = $this->normalizeToSec($gs);
                        $geSec = $this->normalizeToSec($ge);
                        // if the user-typed window can cover a full 3h grid window, force to it
                        if ($rawS <= $gsSec && $rawE >= $geSec) {
                            $startSec = $gsSec;
                            $endSec   = $geSec;
                            $forced   = true;
                            break;
                        }
                    }
                    // if not forced, at least ensure 180 minutes after snapping
                    if (!$forced) {
                        if ($this->diffMinutesSec($startSec, $endSec) < 180) {
                            $this->addError("dayEnd.$d", 'On Friday, availability must be at least 3 hours (e.g., 17:30–20:30).');
                            return;
                        }
                    }
                }

                // persist: ensure a time_slots row exists for the snapped window
                $slotId = $this->ensureTimeSlot($startSec, $endSec);

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

    /** ── Helpers ─────────────────────────────────────────────────────────── */

    /** snap start to the next grid start at or after input */
    private function snapStart(string $hhmm): string
    {
        foreach ($this->gridStarts as $s) {
            if ($hhmm <= $s) return $s;
        }
        // if beyond last start, stay at last start
        return end($this->gridStarts);
    }

    /** snap end to the previous grid end at or before input */
    private function snapEnd(string $hhmm): string
    {
        foreach ($this->gridEnds as $e) {
            if ($hhmm <= $e) return $e;
        }
        // if beyond last end, clamp to last end
        return end($this->gridEnds);
    }

    private function isValidRange(string $start, string $end): bool
    {
        return $start < $end;
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
        if (!$hhmm) return null;
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $hhmm)) return $hhmm;
        if (preg_match('/^\d{2}:\d{2}$/', $hhmm)) return $hhmm . ':00';
        return null;
    }

    // Friday >= 3 hours helper
    private function diffMinutes(string $hhmmStart, string $hhmmEnd): int
    {
        [$sh,$sm] = array_map('intval', explode(':', $hhmmStart));
        [$eh,$em] = array_map('intval', explode(':', $hhmmEnd));
        return ($eh*60 + $em) - ($sh*60 + $sm);
    }

    // diff where inputs are 'HH:MM:SS'
    private function diffMinutesSec(string $secStart, string $secEnd): int
    {
        [$sh,$sm,$ss] = array_map('intval', explode(':', $secStart));
        [$eh,$em,$es] = array_map('intval', explode(':', $secEnd));
        return (int) round((($eh*3600 + $em*60 + $es) - ($sh*3600 + $sm*60 + $ss)) / 60);
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
