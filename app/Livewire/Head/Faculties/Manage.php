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

    // users_department
    public ?string $user_code_id = null;
    public ?string $position = null;        // 'Faculty' | 'Head'
    public ?int $dept_department_id = null; // LOCKED read-only in UI

    // users_employments
    public ?string $employment_classification = null; // Teaching
    public ?string $employment_status = null;         // Full-Time | Part-Time
    public ?int $regular_load = null;
    public ?int $extra_load = null;

    public bool $readOnlyOrg = true;

    /** Part-time availability editor (Mon–Fri only) */
    public array $days = ['MON','TUE','WED','THU','FRI'];

    /** Day toggles */
    public array $dayEnabled = [];

    /** Start/End per day: HH:MM */
    public array $dayStart = [];
    public array $dayEnd   = [];

    /** OPTIONS shown in UI (GRID ONLY) */
    public array $gridStartOptions = [];
    public array $gridEndOptions   = [];

    /** ── GRID WINDOWS USED BY THE SCHEDULER ─────────────── */
    private array $gridStarts = ['07:30','09:00','10:30','13:00','14:30','16:00','17:30','19:00'];
    private array $gridEnds   = ['09:00','10:30','12:00','14:30','16:00','17:30','19:00','20:30'];

    /** valid 3h windows — used for FRI rule */
    private array $gridWindows3h = [
        ['13:00','16:00'],
        ['14:30','17:30'],
        ['16:00','19:00'],
        ['17:30','20:30'],
    ];

    /** PT hard end limit */
    private string $PT_END_LIMIT = '21:00:00';

    public function getIsPartTimeProperty(): bool
    {
        return $this->employment_status === 'Part-Time';
    }

    public function mount(User $user)
    {
        /** @var \App\Models\User|null $me */
        $me = Auth::user();

        // Only Program Head / Chairperson
        abort_unless(in_array($me?->role, ['Head','Chairperson'], true), 403);

        // Scope protection:
        $isSelf     = $user->id === $me->id;
        $sameDept   = $user->department_id === $me->department_id;
        $sameCourse = $me->course_id && $user->course_id === $me->course_id;
        $isFaculty  = $user->role === 'Faculty';

        abort_unless(
            $isSelf || ($isFaculty && $sameDept && $sameCourse),
            403
        );

        // preload
        $this->user = $user->load(['department','course','employment','userDepartment']);

        // UI options (GRID ONLY)
        $this->gridStartOptions = $this->gridStarts;
        $this->gridEndOptions   = $this->gridEnds;

        $this->user_code_id = $this->user->userDepartment->user_code_id ?? null;

        if ($this->user->userDepartment?->position) {
            $this->position = $this->user->userDepartment->position;
        } else {
            $this->position = ($this->user->role === 'Head' || $this->user->role === 'Chairperson')
                ? 'Head'
                : 'Faculty';
        }

        $this->dept_department_id = $this->user->department_id;

        $this->employment_classification = $this->user->employment->employment_classification ?? 'Teaching';
        $this->employment_status         = $this->user->employment->employment_status ?? 'Full-Time';
        $this->regular_load              = $this->user->employment->regular_load ?? 21;
        $this->extra_load                = $this->user->employment->extra_load ?? 6;

        // defaults
        foreach ($this->days as $d) {
            $this->dayEnabled[$d] = false;
            $this->dayStart[$d]   = '17:30';
            $this->dayEnd[$d]     = '20:30';
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
            $this->dayEnabled[$d] = (bool) $rec->is_available;
            $this->dayStart[$d]   = substr($rec->timeSlot->start_time, 0, 5);
            $this->dayEnd[$d]     = substr($rec->timeSlot->end_time, 0, 5);
        }
    }

    public function updatedEmploymentStatus(): void
    {
        // no-op; UI auto re-render
    }

    /** Department save */
    public function saveDepartment()
    {
        $this->validate([
            'user_code_id' => ['nullable','string','max:255'],
            'position'     => ['nullable','string','in:Faculty,Head'],
        ]);

        $record = $this->user->userDepartment()->first()
            ?: new UsersDepartment(['user_id' => $this->user->id]);

        $record->user_code_id  = $this->user_code_id;
        $record->position      = $this->position ?: 'Faculty';
        $record->department_id = $this->user->department_id; // locked
        $record->save();

        session()->flash('success_department', 'Department record updated.');
    }

    /** Employment save */
    public function saveEmployment()
    {
        $this->validate([
            'employment_classification' => ['required','string','max:255'],
            'employment_status'         => ['required','string', Rule::in(['Full-Time','Part-Time'])],
            'regular_load'              => ['nullable','integer','min:0','max:45'],
            'extra_load'                => ['nullable','integer','min:0','max:24'],
        ]);

        $emp = $this->user->employment()->first()
            ?: new UsersEmployment(['user_id' => $this->user->id]);

        $emp->employment_classification = $this->employment_classification;
        $emp->employment_status         = $this->employment_status;
        $emp->regular_load              = $this->regular_load ?? 0;
        $emp->extra_load                = $this->extra_load ?? 0;
        $emp->save();

        session()->flash('success_employment', 'Employment details updated.');
    }

    /**
     * Save: Availability (Part-Time only)
     * Now GRID TIMES ONLY via dropdown. No confusing “snap”.
     */
    public function saveAvailability()
    {
        if (!$this->isPartTime) {
            session()->flash('success_availability', 'Availability input applies to Part-Time faculty only.');
            return;
        }

        // validate enabled days
        $rules = [];
        foreach ($this->days as $d) {
            if (!empty($this->dayEnabled[$d])) {
                $rules["dayStart.$d"] = ['required', Rule::in($this->gridStarts)];
                $rules["dayEnd.$d"]   = ['required', Rule::in($this->gridEnds), 'after:dayStart.'.$d];
            }
        }

        if (!empty($rules)) {
            $this->validate($rules);
        }

        foreach ($this->days as $d) {

            if (!empty($this->dayEnabled[$d])) {

                $start = $this->dayStart[$d] ?? null; // HH:MM
                $end   = $this->dayEnd[$d] ?? null;   // HH:MM
                if (!$start || !$end) continue;

                // FRI: force exact 3-hour window only
                if ($d === 'FRI') {
                    $allowed = false;
                    foreach ($this->gridWindows3h as [$gs, $ge]) {
                        if ($start === $gs && $end === $ge) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) {
                        $this->addError("dayEnd.$d", 'On Friday, choose one 3-hour window: 13:00–16:00, 14:30–17:30, 16:00–19:00, or 17:30–20:30.');
                        return;
                    }
                }

                // Convert to HH:MM:SS for DB
                $startSec = $this->normalizeToSec($start);
                $endSec   = $this->normalizeToSec($end);

                // safety end limit
                if ($endSec > $this->PT_END_LIMIT) {
                    $endSec = $this->PT_END_LIMIT;
                }

                // ensure slot exists
                $slotId = $this->ensureTimeSlot($startSec, $endSec);

                // Save exactly one slot per day (no duplicates)
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

                FacultyAvailability::where('user_id', $this->user->id)
                    ->where('day', $d)
                    ->where('time_slot_id', '!=', $slotId)
                    ->delete();

            } else {
                // disabled day => clear
                FacultyAvailability::where('user_id', $this->user->id)
                    ->where('day', $d)
                    ->delete();
            }
        }

        session()->flash('success_availability', 'Part-Time availability saved (GRID times).');
    }

    /** Ensure unique slot by (start_time, end_time) */
    private function ensureTimeSlot(string $startTime, string $endTime): int
    {
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

    // (kept) helper
    private function diffMinutes(string $hhmmStart, string $hhmmEnd): int
    {
        // BUGFIX: use $sm not $em on subtract
        [$sh,$sm] = array_map('intval', explode(':', $hhmmStart));
        [$eh,$em] = array_map('intval', explode(':', $hhmmEnd));
        return ($eh*60 + $em) - ($sh*60 + $sm);
    }

    private function diffMinutesSec(string $secStart, string $secEnd): int
    {
        [$sh,$sm,$ss] = array_map('intval', explode(':', $secStart));
        [$eh,$em,$es] = array_map('intval', explode(':', $secEnd));
        return (int) round((($eh*3600 + $em*60 + $es) - ($sh*3600 + $sm*60 + $ss)) / 60);
    }

    /** Scheduler helper */
    public static function allowedDaysForFullTimer(): array
    {
        return ['MON','TUE','WED','THU','FRI'];
    }

    public function render()
    {
        return view('livewire.head.faculties.manage', [
            'departments' => Department::where('id', $this->user->department_id)
                ->get(['id','department_name']),
            'roleBadge'   => $this->user->role ?? '—',
        ]);
    }
}
