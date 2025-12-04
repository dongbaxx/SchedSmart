<?php

namespace App\Livewire\Head\Schedulings;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Models\{
    CourseOffering,
    SectionMeeting,
    Room
};

#[Title('Edit Time & Room')]
#[Layout('layouts.head-shell')]
class Edit extends Component
{
    public CourseOffering $offering;

    /**
     * Each row:
     * - single meeting (FRI, SAT, etc.)
     * - or paired MON/WED, TUE/THU (2 ids)
     *
     * @var array<int, array{
     *   meeting_ids: int[],
     *   is_pair: bool,
     *   code: string,
     *   title: string,
     *   faculty: string,
     *   day: string,
     *   start_time: string|null,
     *   end_time: string|null,
     *   room_id: int|null
     * }>
     */
    public array $rows = [];

    /** Blocked windows (e.g. lunch) */
    private array $BLOCKED_WINDOWS = [
        ['12:00', '13:00'],
    ];

    public function mount(CourseOffering $offering): void
{
    if (!$offering->exists) {
        abort(404);
    }

    $this->offering = $offering;

    // load all meetings of this offering
    $meetings = $offering->meetings()
        ->with(['curriculum','faculty','room'])
        ->orderByRaw("FIELD(day,'MON','TUE','WED','THU','FRI','SAT')")
        ->orderBy('start_time')
        ->get();

    // group per subject (curriculum)
    $grouped = $meetings->groupBy('curriculum_id');
    $rows = [];

    foreach ($grouped as $curriculumId => $set) {
        $usedIds = [];

        $buildRow = function ($meetings, bool $isPair, string $dayLabel) {
            /** @var \Illuminate\Support\Collection|\App\Models\SectionMeeting[] $meetings */
            $first = $meetings->first();

            return [
                'meeting_ids' => $meetings->pluck('id')->values()->all(),
                'is_pair'     => $isPair,
                'code'        => $first->curriculum?->course_code ?? '',
                'title'       => $first->curriculum?->descriptive_title ?? '',
                'faculty'     => $first->faculty?->name ?? '',
                'day'         => $dayLabel,
                'start_time'  => substr($first->start_time, 0, 5),
                'end_time'    => substr($first->end_time, 0, 5),
                'room_id'     => $first->room_id,
            ];
        };

        // 1) MON/WED pairs
        $mwBuckets = $set->whereIn('day', ['MON','WED'])
            ->groupBy(fn($m) => $m->start_time.'|'.$m->end_time);

        foreach ($mwBuckets as $bucket) {
            if ($bucket->count() < 2) {
                continue;
            }
            $pair = $bucket->take(2);
            $ids  = $pair->pluck('id')->values()->all();
            $usedIds = array_merge($usedIds, $ids);

            // label as MON (primary) – auto WED
            $rows[] = $buildRow($pair, true, 'MON');
        }

        // 2) TUE/THU pairs
        $ttBuckets = $set->whereIn('day', ['TUE','THU'])
            ->whereNotIn('id', $usedIds)
            ->groupBy(fn($m) => $m->start_time.'|'.$m->end_time);

        foreach ($ttBuckets as $bucket) {
            if ($bucket->count() < 2) {
                continue;
            }
            $pair = $bucket->take(2);
            $ids  = $pair->pluck('id')->values()->all();
            $usedIds = array_merge($usedIds, $ids);

            // label as TUE (primary) – auto THU
            $rows[] = $buildRow($pair, true, 'TUE');
        }

        // 3) MERGE same-day same-time duplicates (e.g. duha ka FRI 08–11)
        $sameDayBuckets = $set->whereNotIn('id', $usedIds)
            ->groupBy(fn($m) => $m->day.'|'.$m->start_time.'|'.$m->end_time);

        foreach ($sameDayBuckets as $bucket) {
            if ($bucket->count() < 2) {
                continue; // kung usa ra, i-uban nalang sa remaining singles
            }

            // treat as "pair" row (multi-meeting row), bisag same day
            $pair = $bucket;
            $ids  = $pair->pluck('id')->values()->all();
            $usedIds = array_merge($usedIds, $ids);

            $dayLabel = $pair->first()->day; // e.g. 'FRI'
            $rows[] = $buildRow($pair, true, $dayLabel);
        }

        // 4) remaining singles (FRI/SAT or unpaired MON/TUE/etc.)
        $remaining = $set->whereNotIn('id', $usedIds);
        foreach ($remaining as $m) {
            $rows[] = $buildRow(collect([$m]), false, $m->day);
        }
    }

    $this->rows = collect($rows)
        ->sortBy([
            ['code', 'asc'],
            ['day', 'asc'],
            ['start_time', 'asc'],
        ])
        ->values()
        ->all();
}


    protected function rules(): array
    {
        return [
            'rows.*.day'        => 'required|in:MON,TUE,WED,THU,FRI,SAT',
            'rows.*.start_time' => 'required|date_format:H:i',
            'rows.*.end_time'   => 'required|date_format:H:i|after:rows.*.start_time',
            'rows.*.room_id'    => 'nullable|exists:rooms,id',
        ];
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    /** Pair day mapping for selector */
    private function pairDaysFromChoice(string $day): array
    {
        switch ($day) {
            case 'MON':
            case 'WED':
                return ['MON','WED'];
            case 'TUE':
            case 'THU':
                return ['TUE','THU'];
            default:
                // single day (FRI/SAT, or others)
                return [$day];
        }
    }

    private function overlaps(string $aS, string $aE, string $bS, string $bE): bool
    {
        return ($aS < $bE) && ($aE > $bS);
    }

    private function blockedByPolicy(string $start, string $end): bool
    {
        foreach ($this->BLOCKED_WINDOWS as [$bs,$be]) {
            if ($this->overlaps($start, $end, $bs, $be)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Faculty conflict – SAME academic year only.
     *
     * $ignoreSameSubject:
     *   true  → ayaw apila ang ubang meetings sa SAME (offering + curriculum)
     *           (para di mag self-conflict ang MON/WED pair)
     */
    private function conflictFacultyForMeeting(
        SectionMeeting $meeting,
        string $day,
        string $start,
        string $end,
        bool $ignoreSameSubject = false
    ): bool {
        if (!$meeting->faculty_id) {
            return false;
        }

        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->where('section_meetings.faculty_id', $meeting->faculty_id)
            ->where('section_meetings.day', $day)
            ->where('section_meetings.id', '!=', $meeting->id)
            ->where('section_meetings.start_time', '<', $end)
            ->where('section_meetings.end_time', '>', $start);

        if (isset($this->offering->academic_id)) {
            $q->where('co.academic_id', $this->offering->academic_id);
        }

        if ($ignoreSameSubject) {
            $offId = (int) $meeting->offering_id;
            $curId = (int) $meeting->curriculum_id;

            $q->where(function ($qq) use ($offId, $curId) {
                $qq->where('section_meetings.offering_id', '!=', $offId)
                   ->orWhere('section_meetings.curriculum_id', '!=', $curId);
            });
        }

        return $q->exists();
    }

    /** Room conflict – SAME academic year only */
    private function conflictRoom(?int $roomId, string $day, string $start, string $end, int $excludeMeetingId): bool
    {
        if (!$roomId) return false;

        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->where('section_meetings.room_id', $roomId)
            ->where('section_meetings.day', $day)
            ->where('section_meetings.id', '!=', $excludeMeetingId)
            ->where('section_meetings.start_time', '<', $end)
            ->where('section_meetings.end_time', '>', $start);

        if (isset($this->offering->academic_id)) {
            $q->where('co.academic_id', $this->offering->academic_id);
        }

        return $q->exists();
    }

    /** Section (offering) conflict – SAME academic year only */
    private function conflictSection(int $offeringId, string $day, string $start, string $end, int $excludeMeetingId): bool
    {
        $q = SectionMeeting::query()
            ->join('course_offerings as co', 'co.id', '=', 'section_meetings.offering_id')
            ->where('section_meetings.offering_id', $offeringId)
            ->where('section_meetings.day', $day)
            ->where('section_meetings.id', '!=', $excludeMeetingId)
            ->where('section_meetings.start_time', '<', $end)
            ->where('section_meetings.end_time', '>', $start);

        if (isset($this->offering->academic_id)) {
            $q->where('co.academic_id', $this->offering->academic_id);
        }

        return $q->exists();
    }

    // ─────────────────────────────────────────────────────────
    // SAVE
    // ─────────────────────────────────────────────────────────

    public function save(): void
{
    $this->validate();

    $hadConflicts = false;

    DB::transaction(function () use (&$hadConflicts) {

        foreach ($this->rows as $index => $row) {
            $ids    = $row['meeting_ids'] ?? [];
            $isPair = !empty($row['is_pair']);
            $day    = $row['day'] ?? null;
            $start  = $row['start_time'] ?? null;
            $end    = $row['end_time'] ?? null;

            if (!$day || !$start || !$end || strlen($start) !== 5 || strlen($end) !== 5 || $start >= $end) {
                continue;
            }
            if (empty($ids)) {
                continue;
            }

            // Policy: avoid lunch block, etc.
            if ($this->blockedByPolicy($start, $end)) {
                $this->addError("rows.$index.start_time", 'This time overlaps with a blocked period (e.g., lunch).');
                $hadConflicts = true;
                continue;
            }

            if ($isPair) {
                // MON/WED or TUE/THU pair OR same-day duplicates (e.g. FRI/Fri)
                $daysForPair = $this->pairDaysFromChoice($day);
                $sameDayPair = count($daysForPair) === 1;   // FRI / SAT bucket

                // 1) CONFLICT CHECKS
                foreach ($ids as $idx => $meetingId) {
                    $meeting = SectionMeeting::where('offering_id', $this->offering->id)
                        ->where('id', $meetingId)
                        ->first();

                    if (!$meeting) {
                        continue;
                    }

                    $dayForThis = $daysForPair[min($idx, count($daysForPair) - 1)];
                    $offeringId = (int) $meeting->offering_id;
                    $roomIdNew  = $row['room_id'] ?: null;

                    if ($sameDayPair) {
                        // 🌟 Special case:
                        // same subject, same day, same time (duplicates) →
                        // only faculty conflict; ignore section/room self-conflict.
                        $hasFacultyConflict = $this->conflictFacultyForMeeting(
                            $meeting,
                            $dayForThis,
                            $start,
                            $end,
                            true   // ignore same subject in this pair
                        );
                        $hasRoomConflict    = false;
                        $hasSectionConflict = false;
                    } else {
                        // Normal MON/WED or TUE/THU pair
                        $hasFacultyConflict = $this->conflictFacultyForMeeting(
                            $meeting,
                            $dayForThis,
                            $start,
                            $end,
                            false
                        );
                        $hasRoomConflict    = $this->conflictRoom($roomIdNew, $dayForThis, $start, $end, $meetingId);
                        $hasSectionConflict = $this->conflictSection($offeringId, $dayForThis, $start, $end, $meetingId);
                    }

                    if ($hasFacultyConflict || $hasRoomConflict || $hasSectionConflict) {
                        $this->addError(
                            "rows.$index.day",
                            "Cannot save. Conflict detected for {$row['code']} ({$row['faculty']}) on {$dayForThis} {$start}-{$end}."
                        );
                        $hadConflicts = true;
                        continue 2; // skip whole pair
                    }
                }

                // 2) UPDATE ALL MEETINGS IN THE ROW
                $daysForPair = $this->pairDaysFromChoice($day);

                foreach ($ids as $idx => $meetingId) {
                    $meeting = SectionMeeting::where('offering_id', $this->offering->id)
                        ->where('id', $meetingId)
                        ->first();

                    if (!$meeting) {
                        continue;
                    }

                    $dayForThis = $daysForPair[min($idx, count($daysForPair) - 1)];

                    $update = [
                        'day'        => $dayForThis,
                        'start_time' => $start,
                        'end_time'   => $end,
                        'room_id'    => $row['room_id'] ?: null,
                        'notes'      => null,
                        'updated_at' => now(),
                    ];

                    $meeting->update($update);
                }

            } else {
                // SINGLE MEETING
                $meetingId = $ids[0];

                $meeting = SectionMeeting::where('offering_id', $this->offering->id)
                    ->where('id', $meetingId)
                    ->first();

                if (!$meeting) {
                    continue;
                }

                $offeringId = (int) $meeting->offering_id;
                $roomIdNew  = $row['room_id'] ?: null;

                $hasFacultyConflict = $this->conflictFacultyForMeeting(
                    $meeting,
                    $day,
                    $start,
                    $end,
                    false
                );
                $hasRoomConflict    = $this->conflictRoom($roomIdNew, $day, $start, $end, $meetingId);
                $hasSectionConflict = $this->conflictSection($offeringId, $day, $start, $end, $meetingId);

                if ($hasFacultyConflict || $hasRoomConflict || $hasSectionConflict) {
                    $this->addError(
                        "rows.$index.day",
                        "Cannot save. Conflict detected for {$row['code']} ({$row['faculty']}) on {$day} {$start}-{$end}."
                    );
                    $hadConflicts = true;
                    continue;
                }

                $update = [
                    'day'        => $day,
                    'start_time' => $start,
                    'end_time'   => $end,
                    'room_id'    => $row['room_id'] ?: null,
                    'notes'      => null,
                    'updated_at' => now(),
                ];

                $meeting->update($update);
            }
        }
    });

    if ($hadConflicts) {
        session()->flash('warning', 'Some changes were not saved because of conflicts (faculty, room, or section). Please review highlighted rows.');
    } else {
        session()->flash('success', 'Schedule updated successfully.');
    }
}


    public function render()
    {
        $rooms = Room::orderBy('code')->get();

        return view('livewire.head.schedulings.edit', compact('rooms'));
    }
}
