    @php
        use Illuminate\Support\Str;

        // ---------- Program Name fallback (safe across different column names/relations) ----------
        $programName =
            data_get($offering,'course.name')
            ?? data_get($offering,'course.course_name')
            ?? data_get($offering,'course.program_name')
            ?? data_get($offering,'course.title')
            ?? data_get($offering,'course.course')
            ?? data_get($offering,'course.abbr')
            ?? data_get($offering,'course.code')
            ?? data_get($offering,'course.course_code')
            ?? '—';

        // ---------- Year level label ----------
        $rawYear = (string)($offering->year_level ?? '');
        $y = strtoupper(trim($rawYear));

        $yearLabel = match (true) {
            in_array($y, ['1','1ST','FIRST','FIRST YEAR','YEAR 1'], true) => 'First Year',
            in_array($y, ['2','2ND','SECOND','SECOND YEAR','YEAR 2'], true) => 'Second Year',
            in_array($y, ['3','3RD','THIRD','THIRD YEAR','YEAR 3'], true) => 'Third Year',
            in_array($y, ['4','4TH','FOURTH','FOURTH YEAR','YEAR 4'], true) => 'Fourth Year',
            default => ($rawYear !== '' ? $rawYear : '—'),
        };

        $sectionName = data_get($offering,'section.section_name','—');
    @endphp

    <div class="space-y-6 print-root">

        {{-- ===================== PRINT & SCREEN STYLES (from your layout) ===================== --}}
        <style>
        @media print {
        nav, aside, header, footer, .navbar, .sidebar, .print\:hidden { display:none !important; visibility:hidden !important; }
        @page { margin: 1in 1in 2in 1in; }
        html, body { margin:0!important; padding:0!important; background:#fff!important; height:auto!important; -webkit-print-color-adjust:exact; print-color-adjust:exact; overflow:visible!important; }
        .container, .max-w-7xl, .mx-auto, .sm\:px-6, .lg\:px-8, .print-root, .page, .p-6, .py-6, .py-8, .py-12, .px-6, .px-8, [class*="shadow"], [class*="ring-"], [class*="border-"] {
            background:#fff!important; border:none!important; box-shadow:none!important; outline:none!important; margin:0!important; padding:0!important;
        }
        .page { page-break-after: always; display:block; width:100%; height:auto!important; margin:0 auto; padding:0; }
        .page-inner { display:block; width:100%; height:auto; margin:0 auto; padding:0; background:#fff!important; box-sizing:border-box; page-break-inside: avoid; }
        .header-image { margin-bottom: 4px; }
        .header-image img { max-height:70px; height:auto; display:block; margin:0 auto 2px; }

        .meta-info { width:90%; margin:4px auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Courier New", monospace; font-size:13px; }
        .meta-row { display:flex; justify-content:space-between; margin-bottom:2px; width:100%; }
        .meta-row .left { padding-left:26mm; }
        .meta-row .right { padding-right:26mm; text-align:right; }
        .h-title { font-size:22px; font-weight:700; text-align:center; margin:4px 0 8px; }

        .table { width:90%!important; border-collapse:collapse!important; background:#fff!important; margin:0 auto; page-break-inside: avoid; }
        .table th, .table td { border:1px solid #1f2937!important; padding:4px 6px!important; font-size:11px!important; line-height:1.15!important; height:22px!important; vertical-align:middle!important; }
        .table th { background:#ffffff!important; font-size:12px!important; height:24px!important; }

        .fixed, .sticky, [class*="sticky"], [class*="fixed"] { position: static !important; }
        [class*="h-screen"], [class*="min-h-screen"], [style*="height:100vh"], [style*="min-height:100vh"] { height:auto!important; min-height:0!important; }
        * { overflow:visible!important; }
        }

        body { background-color:#fbfaf8!important; }
        .print-root, main, .p-6 { background-color:#fbfaf8!important; box-shadow:0 2px 8px rgba(0,0,0,.04); padding:20px; }
        .header-image { text-align:center; margin-bottom:6px; }
        .header-image img { height:85px; object-fit:contain; margin:0 auto; display:inline-block; }
        .meta-info { width:100%; margin:6px 0 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size:14px; }
        .meta-row { display:flex; justify-content:space-between; margin-bottom:2px; }
        .meta-row .left  { padding-left:2.5in; }
        .meta-row .right { padding-right:2.5in; text-align:left; }
        .h-title { font-size:28px; font-weight:700; text-align:center; margin:6px 0 10px; }
        .table { width:90%; border-collapse:collapse; background-color:#ffffff; }
        .table th, .table td {
        border:1px solid #1f2937;
        padding:6px 10px;
        font-size:14px;
        line-height:1.3;
        vertical-align:middle;
        text-align:left;
        height:40px;
        }
        .table th { background:#f9fafb; font-weight:600; }
        .table td:nth-child(3),
        .table td:nth-child(4),
        .table td:nth-child(5),
        .table td:nth-child(6),
        .table td:nth-child(7),
        .table td:nth-child(8) { text-align:center; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        </style>

        {{-- ===================== SCREEN UI (hidden during printing) ===================== --}}
        <div class="print:hidden space-y-6">

            {{-- TOP BAR --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold">Edit Time &amp; Room</h1>
                    <p class="text-xs text-gray-500">
                        Program Name: <b>{{ $programName }}</b>
                        • Year Level: <b>{{ $yearLabel }}</b>
                        • A.Y.: <b>{{ data_get($offering,'academic.school_year','—') }}</b>
                        • Semester: <b>{{ data_get($offering,'academic.semester','—') }}</b>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    {{-- ✅ Print button ALWAYS visible --}}
                    <button type="button"
                            onclick="window.print()"
                            class="px-3 py-2 rounded-lg text-sm bg-gray-800 text-white hover:bg-gray-900">
                        Print
                    </button>

                    <button type="button"
                            wire:click="loadPlan"
                            wire:loading.attr="disabled"
                            wire:target="loadPlan"
                            class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-sm">
                        <span wire:loading.remove wire:target="loadPlan">Reload</span>
                        <span wire:loading wire:target="loadPlan">Reloading…</span>
                    </button>
                </div>
            </div>

            {{-- ALERTS --}}
            @if(session('success'))
                <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-3 py-2 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('offerings_warning'))
                <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm">
                    {{ session('offerings_warning') }}
                </div>
            @endif

            {{-- SUBJECT TABLE --}}
            <div class="rounded-xl border p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">
                        Subjects
                        <span class="ml-2 text-xs text-gray-500">({{ count($plan ?? []) }} total)</span>
                    </h2>

                    <div class="flex items-center gap-2">
                        @if(!$alreadyGenerated)
                            <button wire:click="generate"
                                    wire:loading.attr="disabled"
                                    wire:target="generate"
                                    class="px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                                <span wire:loading.remove wire:target="generate">Generate</span>
                                <span wire:loading wire:target="generate">Generating…</span>
                            </button>
                        @else
                            <button wire:click="regenerateSection"
                                    wire:loading.attr="disabled"
                                    wire:target="regenerateSection"
                                    class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Regenerate
                            </button>

                            <button wire:click="cancelSection"
                                    wire:loading.attr="disabled"
                                    wire:target="cancelSection"
                                    class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Cancel
                            </button>
                        @endif
                    </div>
                </div>

                @if(!$planLoaded)
                    <p class="text-sm text-gray-500">Loading…</p>
                @elseif(empty($plan))
                    <p class="text-sm text-gray-500">No subjects found.</p>
                @else

                <div class="overflow-x-auto border rounded-xl">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left">Subject</th>
                                <th class="px-3 py-2 text-left">Type</th>
                                <th class="px-3 py-2 text-left">Units</th>
                                <th class="px-3 py-2 text-left">Faculty</th>
                                <th class="px-3 py-2 text-left">Room</th>
                                <th class="px-3 py-2 text-left">Day(s)</th>
                                <th class="px-3 py-2 text-left">Start</th>
                                <th class="px-3 py-2 text-left">End</th>
                                <th class="px-3 py-2 text-left">Manual</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y">
                        @foreach($plan as $i => $row)
                            @php
                                $fid = $row['faculty_id'] ?? null;
                                $rid = $row['room_id'] ?? null;

                                $f = $fid ? $faculty->firstWhere('id', $fid) : null;
                                $r = $rid ? $rooms->firstWhere('id', $rid) : null;

                                $isInc = empty($fid);

                                $dayStr = (string)($row['day'] ?? '');
                                $isPair = $dayStr && Str::contains($dayStr, '/');

                                $canManualEdit = !empty($row['faculty_id'])
                                    && !empty($row['day'])
                                    && !empty($row['start_time'])
                                    && !empty($row['end_time']);
                            @endphp

                            <tr wire:key="row-{{ $row['curriculum_id'] ?? $i }}">
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium">
                                        {{ $row['code'] ?? '—' }} — {{ $row['title'] ?? '—' }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Spec: {{ $row['specialization'] ?? '—' }}
                                        • Room Type: {{ $row['type'] ?? '—' }}
                                    </div>
                                </td>

                                <td class="px-3 py-2 align-top">{{ $row['type'] ?? '—' }}</td>
                                <td class="px-3 py-2 align-top">{{ $row['units'] ?? '—' }}</td>

                                <td class="px-3 py-2 align-top">
                                    @if($isInc)
                                        <span class="text-red-600 font-semibold">INC</span>
                                    @else
                                        {{ $f?->name ?? '—' }}
                                    @endif
                                </td>

                                <td class="px-3 py-2 align-top">{{ $r?->code ?? '—' }}</td>

                                <td class="px-3 py-2 align-top">
                                    {{ $row['day'] ?? '—' }}
                                    @if($isPair)
                                        <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">pair</span>
                                    @endif
                                </td>

                                <td class="px-3 py-2 align-top">{{ $row['start_time'] ?? '—' }}</td>
                                <td class="px-3 py-2 align-top">{{ $row['end_time'] ?? '—' }}</td>

                                <td class="px-3 py-2 align-top">
                                    <button type="button"
                                            @if($canManualEdit)
                                                wire:click="openManual({{ (int)($row['curriculum_id'] ?? 0) }})"
                                                class="px-3 py-2 rounded-lg border text-xs hover:bg-gray-50"
                                            @else
                                                disabled
                                                class="px-3 py-2 rounded-lg border text-xs bg-gray-50 text-gray-400 cursor-not-allowed"
                                            @endif
                                            title="{{ $canManualEdit ? 'Manual Edit' : 'Not editable: INC / not assigned' }}">
                                        Manual Edit
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>

                        <tfoot>
                            <tr class="border-t bg-gray-50">
                                <td colspan="2" class="px-3 py-2 text-right text-xs text-gray-500"><b>Total Units:</b></td>
                                <td class="px-3 py-2 text-sm"><b>{{ $totalUnits }}</b></td>
                                <td colspan="6"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @endif
            </div>

            {{-- ========================= MANUAL EDIT MODAL (UNCHANGED) ========================= --}}
            @if($editingCid)
                @php
                    $curRow = collect($plan ?? [])->firstWhere('curriculum_id', $editingCid);

                    $curFacultyName = $curRow && !empty($curRow['faculty_id'])
                        ? ($faculty->firstWhere('id', $curRow['faculty_id'])?->name ?? '—')
                        : 'INC';

                    $curRoomCode = $curRow && !empty($curRow['room_id'])
                        ? ($rooms->firstWhere('id', $curRow['room_id'])?->code ?? '—')
                        : '—';

                    $curDay = $curRow['day'] ?? '—';
                    $curStart = $curRow['start_time'] ?? '—';
                    $curEnd = $curRow['end_time'] ?? '—';

                    $mode = strtoupper($edit['day_mode'] ?? 'PAIR');
                    $selectedFacultyId = (int)($edit['faculty_id'] ?? 0);
                    $selectedRoomId = (int)($edit['room_id'] ?? 0);
                    $selectedSlotId = (int)($edit['time_slot_id'] ?? 0);

                    $isFacultyOk = $selectedFacultyId > 0 && (($facultyStatuses[$selectedFacultyId]['ok'] ?? false) === true);
                    $isRoomOk = $selectedRoomId > 0 && (($roomPickStatuses[$selectedRoomId]['ok'] ?? false) === true);

                    $days = $edit['days'] ?? [];
                    $selectedDayKey = $mode === 'PAIR'
                        ? (implode('/', $days ?: ['MON','WED']))
                        : (($days[0] ?? '') ?: '');

                    $isDayOk = $selectedDayKey && (($dayPickStatuses[$selectedDayKey]['ok'] ?? false) === true);
                    $isTimeOk = $selectedSlotId > 0 && (($timePickStatuses[$selectedSlotId]['ok'] ?? false) === true);

                    $steps = ['FACULTY'=>1,'ROOM'=>2,'DAY'=>3,'TIME'=>4];
                    $stepNo = $steps[$pickStep] ?? 1;
                    $progress = (int)(($stepNo / 4) * 100);
                    $canSave = $isFacultyOk && $isRoomOk && $isDayOk && $isTimeOk;
                @endphp

                <div class="fixed inset-0 z-50 flex items-center justify-center">
                    <div class="absolute inset-0 bg-black/30" wire:click="closeManual"></div>

                    <div class="relative w-full max-w-5xl bg-white rounded-2xl shadow-xl border overflow-hidden">
                        <div class="p-4 border-b flex items-center justify-between gap-3">
                            <div>
                                <div class="text-lg font-semibold">Manual Edit Wizard</div>
                                <div class="text-xs text-gray-500">
                                    ✅ Full-Time and Part-Time both follow <b>faculty_availabilities</b> + <b>time_slots</b>.
                                </div>
                            </div>

                            <button type="button"
                                    wire:click="closeManual"
                                    class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                Close
                            </button>
                        </div>

                        <div class="px-4 pt-3">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>Step {{ $stepNo }} of 4</span>
                                <span>{{ $progress }}%</span>
                            </div>
                            <div class="mt-2 h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-indigo-600" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        @if($editWarning)
                            <div class="mx-4 mt-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm">
                                {{ $editWarning }}
                            </div>
                        @endif

                        <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-1 space-y-4">
                                <div class="rounded-xl border p-4">
                                    <div class="font-semibold mb-2">Current Schedule</div>
                                    <div class="text-sm space-y-2">
                                        <div>
                                            <div class="text-xs text-gray-500">Faculty</div>
                                            <div class="font-medium {{ $curFacultyName==='INC' ? 'text-red-600' : '' }}">{{ $curFacultyName }}</div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <div class="text-xs text-gray-500">Room</div>
                                                <div class="font-medium">{{ $curRoomCode }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">Day(s)</div>
                                                <div class="font-medium">{{ $curDay }}</div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <div class="text-xs text-gray-500">Start</div>
                                                <div class="font-medium">{{ $curStart }}</div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500">End</div>
                                                <div class="font-medium">{{ $curEnd }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-xl border p-4">
                                    <div class="font-semibold mb-2">Your Selection</div>

                                    <div class="text-sm space-y-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="text-xs text-gray-500">Mode</div>
                                                <div class="font-medium">{{ $mode==='SINGLE' ? 'SINGLE (3h)' : 'PAIR (90m/day)' }}</div>
                                            </div>
                                            <div class="flex items-center gap-1 rounded-lg border p-1">
                                                <button type="button" wire:click="setModePair"
                                                        class="px-2 py-1 rounded text-xs {{ $mode==='PAIR' ? 'bg-indigo-600 text-white' : 'hover:bg-gray-50' }}">
                                                    Pair
                                                </button>
                                                <button type="button" wire:click="setModeSingle"
                                                        class="px-2 py-1 rounded text-xs {{ $mode==='SINGLE' ? 'bg-indigo-600 text-white' : 'hover:bg-gray-50' }}">
                                                    Single
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="text-xs text-gray-500">Faculty</div>
                                            <div class="font-medium">
                                                {{ $selectedFacultyId ? ($faculty->firstWhere('id',$selectedFacultyId)?->name ?? '—') : '—' }}
                                                @if($selectedFacultyId)
                                                    <span class="ml-2 text-xs font-semibold {{ $isFacultyOk ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $isFacultyOk ? 'OK' : 'NOT OK' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div>
                                            <div class="text-xs text-gray-500">Room</div>
                                            <div class="font-medium">
                                                {{ $selectedRoomId ? ($rooms->firstWhere('id',$selectedRoomId)?->code ?? '—') : '—' }}
                                                @if($selectedRoomId)
                                                    <span class="ml-2 text-xs font-semibold {{ $isRoomOk ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $isRoomOk ? 'OK' : 'NOT OK' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div>
                                            <div class="text-xs text-gray-500">Day(s)</div>
                                            <div class="font-medium">
                                                {{ $selectedDayKey ?: '—' }}
                                                @if($selectedDayKey)
                                                    <span class="ml-2 text-xs font-semibold {{ $isDayOk ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $isDayOk ? 'OK' : 'NOT OK' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div>
                                            <div class="text-xs text-gray-500">Time</div>
                                            <div class="font-medium">
                                                {{ $edit['start_time'] ?? '—' }} – {{ $edit['end_time'] ?? '—' }}
                                                @if($selectedSlotId)
                                                    <span class="ml-2 text-xs font-semibold {{ $isTimeOk ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $isTimeOk ? 'OK' : 'NOT OK' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-[11px] text-gray-500">
                                        Save will be enabled only if all items are <b class="text-green-700">OK</b>.
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-2 rounded-xl border p-4">
                                <div class="mb-3">
                                    @if($pickStep === 'FACULTY')
                                        <div class="text-lg font-semibold">Step 1: Choose Faculty</div>
                                        <div class="text-sm text-gray-500">
                                            Pick a GREEN faculty. Red means loaded/maxed OR <b>no availability set</b>.
                                        </div>
                                    @elseif($pickStep === 'ROOM')
                                        <div class="text-lg font-semibold">Step 2: Choose Room</div>
                                        <div class="text-sm text-gray-500">Pick a GREEN room (has at least one valid match with faculty).</div>
                                    @elseif($pickStep === 'DAY')
                                        <div class="text-lg font-semibold">Step 3: Choose Day</div>
                                        <div class="text-sm text-gray-500">
                                            @if($mode === 'PAIR')
                                                Choose <b>MON/WED</b> or <b>TUE/THU</b>.
                                            @else
                                                Choose one day (SINGLE = 3 hours).
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-lg font-semibold">Step 4: Choose Time</div>
                                        <div class="text-sm text-gray-500">Pick a GREEN time. Red means conflict or not available.</div>
                                    @endif
                                </div>

                                {{-- (Everything below stays exactly as your original modal body) --}}
                                @if($pickStep === 'FACULTY')
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-[360px] overflow-auto">
                                        @foreach($facultyStatuses as $fid => $st)
                                            @php
                                                $ok = (bool)($st['ok'] ?? false);
                                                $selected = ((int)$fid === $selectedFacultyId);
                                            @endphp

                                            <button type="button"
                                                    wire:click="pickFaculty({{ (int)$fid }})"
                                                    @if(!$ok) disabled @endif
                                                    class="w-full text-left rounded-xl border px-3 py-3 transition
                                                        {{ $ok ? 'hover:bg-green-50 border-green-200' : 'bg-red-50 border-red-200 opacity-70 cursor-not-allowed' }}
                                                        {{ $selected ? 'ring-2 ring-indigo-500' : '' }}">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-semibold">{{ $st['name'] ?? '—' }}</div>
                                                    <span class="text-xs font-semibold {{ $ok ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $ok ? 'GREEN' : 'RED' }}
                                                    </span>
                                                </div>
                                                @if(!empty($st['reason']))
                                                    <div class="text-[11px] text-gray-600 mt-1">{{ $st['reason'] }}</div>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>

                                @elseif($pickStep === 'ROOM')
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-[360px] overflow-auto">
                                        @foreach($roomPickStatuses as $rid => $st)
                                            @php
                                                $ok = (bool)($st['ok'] ?? false);
                                                $selected = ((int)$rid === $selectedRoomId);
                                            @endphp

                                            <button type="button"
                                                    wire:click="pickRoom({{ (int)$rid }})"
                                                    @if(!$ok) disabled @endif
                                                    title="{{ $st['reason'] ?? '' }}"
                                                    class="rounded-xl border px-3 py-3 text-left transition
                                                        {{ $ok ? 'hover:bg-green-50 border-green-200' : 'bg-red-50 border-red-200 opacity-70 cursor-not-allowed' }}
                                                        {{ $selected ? 'ring-2 ring-indigo-500' : '' }}">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-semibold">{{ $st['code'] ?? '—' }}</div>
                                                    <span class="text-xs font-semibold {{ $ok ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $ok ? 'GREEN' : 'RED' }}
                                                    </span>
                                                </div>
                                                @if(!$ok && !empty($st['reason']))
                                                    <div class="text-[11px] text-gray-600 mt-1">{{ $st['reason'] }}</div>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>

                                @elseif($pickStep === 'DAY')
                                    @if($mode === 'PAIR')
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach(['MON/WED','TUE/THU'] as $key)
                                                @php
                                                    $st = $dayPickStatuses[$key] ?? ['ok'=>false,'reason'=>'No match'];
                                                    $ok = (bool)($st['ok'] ?? false);
                                                    $selected = ($selectedDayKey === $key);
                                                @endphp

                                                <button type="button"
                                                        wire:click="pickDay('{{ $key }}')"
                                                        @if(!$ok) disabled @endif
                                                        title="{{ $st['reason'] ?? '' }}"
                                                        class="rounded-xl border px-4 py-4 text-left transition
                                                            {{ $ok ? 'hover:bg-green-50 border-green-200' : 'bg-red-50 border-red-200 opacity-70 cursor-not-allowed' }}
                                                            {{ $selected ? 'ring-2 ring-indigo-500' : '' }}">
                                                    <div class="flex items-center justify-between">
                                                        <div class="font-semibold text-base">{{ $key }}</div>
                                                        <span class="text-xs font-semibold {{ $ok ? 'text-green-700' : 'text-red-700' }}">
                                                            {{ $ok ? 'GREEN' : 'RED' }}
                                                        </span>
                                                    </div>
                                                    @if(!$ok && !empty($st['reason']))
                                                        <div class="text-[11px] text-gray-600 mt-1">{{ $st['reason'] }}</div>
                                                    @endif
                                                    <div class="mt-2 text-xs text-gray-500">PAIR = 90 minutes per day</div>
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
                                            @foreach(['MON','TUE','WED','THU','FRI','SAT'] as $key)
                                                @php
                                                    $st = $dayPickStatuses[$key] ?? ['ok'=>false,'reason'=>'No match'];
                                                    $ok = (bool)($st['ok'] ?? false);
                                                    $selected = ($selectedDayKey === $key);
                                                @endphp

                                                <button type="button"
                                                        wire:click="pickDay('{{ $key }}')"
                                                        @if(!$ok) disabled @endif
                                                        title="{{ $st['reason'] ?? '' }}"
                                                        class="rounded-xl border px-3 py-3 text-center transition
                                                            {{ $ok ? 'hover:bg-green-50 border-green-200' : 'bg-red-50 border-red-200 opacity-70 cursor-not-allowed' }}
                                                            {{ $selected ? 'ring-2 ring-indigo-500' : '' }}">
                                                    <div class="font-semibold">{{ $key }}</div>
                                                    <div class="text-[10px] font-semibold {{ $ok ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $ok ? 'GREEN' : 'RED' }}
                                                    </div>
                                                </button>
                                            @endforeach
                                        </div>
                                        <div class="mt-2 text-xs text-gray-500">SINGLE = 3 hours (one day only)</div>
                                    @endif

                                @else
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-[360px] overflow-auto">
                                        @foreach($timePickStatuses as $sid => $st)
                                            @php
                                                $ok = (bool)($st['ok'] ?? false);
                                                $selected = ((int)$sid === $selectedSlotId);
                                            @endphp

                                            <button type="button"
                                                    wire:click="pickTime({{ (int)$sid }})"
                                                    @if(!$ok) disabled @endif
                                                    title="{{ $st['reason'] ?? '' }}"
                                                    class="rounded-xl border px-3 py-3 text-left transition
                                                        {{ $ok ? 'hover:bg-green-50 border-green-200' : 'bg-red-50 border-red-200 opacity-70 cursor-not-allowed' }}
                                                        {{ $selected ? 'ring-2 ring-indigo-500' : '' }}">
                                                <div class="flex items-center justify-between">
                                                    <div class="font-semibold">{{ $st['label'] ?? '—' }}</div>
                                                    <span class="text-xs font-semibold {{ $ok ? 'text-green-700' : 'text-red-700' }}">
                                                        {{ $ok ? 'GREEN' : 'RED' }}
                                                    </span>
                                                </div>
                                                @if(!$ok && !empty($st['reason']))
                                                    <div class="text-[11px] text-gray-600 mt-1">{{ $st['reason'] }}</div>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-5 pt-4 border-t flex items-center justify-between">
                                    <button type="button"
                                            wire:click="backToFaculty"
                                            class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                                        Reset (back to Step 1)
                                    </button>

                                    <div class="flex items-center gap-2">
                                        <button type="button"
                                                wire:click="saveManual"
                                                class="px-4 py-2 rounded-lg text-sm text-white
                                                    {{ $canSave ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-400 cursor-not-allowed' }}"
                                                @if(!$canSave) disabled @endif>
                                            Save Changes
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-2 text-[11px] text-gray-500">
                                    RED means: conflict / not available in faculty_availabilities / duration rule (PAIR=90m, SINGLE=3h).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <p class="text-[11px] text-gray-400 text-center">
                © {{ date('Y') }} SchedSmart. All rights reserved.
            </p>

        </div>

        {{-- ===================== PRINT VIEW (visible only when printing) ===================== --}}
        <div class="hidden print:block">
            @php
                // quick lookups for print
                $fMap = $faculty->pluck('name','id')->all();
                $rMap = $rooms->pluck('code','id')->all();
            @endphp

            <div class="page">
                <div class="page-inner">

                    <div class="header-image">
                        <img src="{{ asset('images/sfxc_header.png') }}" alt="St. Francis Xavier College Header">
                    </div>

                    <div class="meta-info">
                        <div class="meta-row">
                            <div class="left">School Year : <strong>{{ data_get($offering,'academic.school_year','—') }}</strong></div>
                            <div class="right">Semester : <strong>{{ data_get($offering,'academic.semester','—') }}</strong></div>
                        </div>
                        <div class="meta-row">
                            <div class="left">Program Name : <strong>{{ $programName }}</strong></div>
                            <div class="right">Year Level : <strong>{{ $yearLabel }}</strong></div>
                        </div>
                    </div>

                    {{-- Center title = SECTION NAME --}}
                    <div class="h-title">{{ $sectionName }}</div>

                    {{-- ✅ 1) SEPARATE HEADER ROW (LIKE YOUR CIRCLED SAMPLE) --}}
                    <table class="table" style="margin:0 auto 0;">
                        <thead>
                            <tr>
                                <th style="width:110px;">Course Code</th>
                                <th style="width:320px;">Descriptive Title</th>
                                <th style="width:55px; text-align:center;">Units</th>
                                <th style="width:100px; text-align:center;">Start Time</th>
                                <th style="width:100px; text-align:center;">End Time</th>
                                <th style="width:90px; text-align:center;">Days</th>
                                <th style="width:90px; text-align:center;">Room</th>
                                <th style="width:180px; text-align:center;">Instructor</th>
                            </tr>
                        </thead>
                    </table>

                    {{-- ✅ 2) ALL Term TEXT --}}
                    <div class="meta-info" style="width:90%; margin:6px auto 4px; font-weight:700; font-size:12px;">
                        ALL Term
                    </div>

                    {{-- ✅ 3) BODY TABLE (NO THEAD) --}}
                    <table class="table" style="margin:0 auto;">
                        <tbody>
                            @forelse($plan as $row)
                                @php
                                    $isInc = empty($row['faculty_id']);
                                    $inst  = $isInc ? 'INC' : ($fMap[$row['faculty_id']] ?? '—');
                                    $room  = $isInc ? '—' : ($rMap[$row['room_id']] ?? '—');
                                    $st    = $isInc ? '—' : ($row['start_time'] ?? '—');
                                    $et    = $isInc ? '—' : ($row['end_time'] ?? '—');
                                    $days  = $isInc ? '—' : ($row['day'] ?? '—');
                                @endphp

                                <tr>
                                    <td class="mono" style="width:110px;">{{ $row['code'] ?? '—' }}</td>
                                    <td style="width:320px;">{{ ucfirst((string)($row['title'] ?? '—')) }}</td>
                                    <td class="mono" style="width:55px; text-align:center;">{{ $row['units'] ?? '—' }}</td>
                                    <td class="mono" style="width:100px; text-align:center;">{{ $st }}</td>
                                    <td class="mono" style="width:100px; text-align:center;">{{ $et }}</td>
                                    <td class="mono" style="width:90px; text-align:center;">{{ $days }}</td>
                                    <td class="mono" style="width:90px; text-align:center;">{{ $room }}</td>
                                    <td class="mono" style="width:180px; text-align:center;">{{ $inst }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" style="text-align:center;color:#6b7280;">
                                        No schedules found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="meta-info" style="width:90%;margin:8px auto 0;">
                    </div>

                </div>
            </div>
        </div>

    </div>
