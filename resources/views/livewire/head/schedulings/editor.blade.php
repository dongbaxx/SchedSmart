@php
    use Illuminate\Support\Str;
@endphp

<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Edit Time &amp; Room</h1>
            <p class="text-xs text-gray-500">
                Section: <b>{{ data_get($offering,'section.section_name','—') }}</b>
                • Year Level: <b>{{ $offering->year_level ?? '—' }}</b>
                • A.Y.: <b>{{ data_get($offering,'academic.school_year','—') }}</b>
                • Semester: <b>{{ data_get($offering,'academic.semester','—') }}</b>
            </p>
        </div>

        <button type="button"
                wire:click="loadPlan"
                wire:loading.attr="disabled"
                wire:target="loadPlan"
                class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-sm">
            <span wire:loading.remove wire:target="loadPlan">Reload</span>
            <span wire:loading wire:target="loadPlan">Reloading…</span>
        </button>
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
                    </tr>
                </thead>

                <tbody class="divide-y">
                @foreach($plan as $i => $row)
                    @php
                        $fid = $row['faculty_id'] ?? null;
                        $rid = $row['room_id'] ?? null;

                        $f = $fid ? $faculty->firstWhere('id', $fid) : null;
                        $r = $rid ? $rooms->firstWhere('id', $rid) : null;

                        // ✅ INC RULE: walay faculty = INC
                        $isInc = empty($fid);

                        $dayStr = (string)($row['day'] ?? '');
                        $isPair = $dayStr && Str::contains($dayStr, '/');
                    @endphp

                    <tr wire:key="row-{{ $row['curriculum_id'] ?? $i }}">
                        {{-- SUBJECT --}}
                        <td class="px-3 py-2 align-top">
                            <div class="font-medium">
                                {{ $row['code'] ?? '—' }} — {{ $row['title'] ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-500">
                                Spec: {{ $row['specialization'] ?? '—' }}
                                • Room Type: {{ $row['type'] ?? '—' }}
                            </div>
                        </td>

                        {{-- TYPE --}}
                        <td class="px-3 py-2 align-top">{{ $row['type'] ?? '—' }}</td>

                        {{-- UNITS --}}
                        <td class="px-3 py-2 align-top">{{ $row['units'] ?? '—' }}</td>

                        {{-- FACULTY (INC HERE ONLY) --}}
                        <td class="px-3 py-2 align-top">
                            @if($isInc)
                                <span class="text-red-600 font-semibold">INC</span>
                            @else
                                {{ $f?->name ?? '—' }}
                            @endif
                        </td>

                        {{-- ROOM --}}
                        <td class="px-3 py-2 align-top">{{ $r?->code ?? '—' }}</td>

                        {{-- DAY --}}
                        <td class="px-3 py-2 align-top">
                            {{ $row['day'] ?? '—' }}
                            @if($isPair)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">pair</span>
                            @endif
                        </td>

                        {{-- START --}}
                        <td class="px-3 py-2 align-top">{{ $row['start_time'] ?? '—' }}</td>

                        {{-- END --}}
                        <td class="px-3 py-2 align-top">{{ $row['end_time'] ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>

                <tfoot>
                    <tr class="border-t bg-gray-50">
                        <td colspan="2" class="px-3 py-2 text-right text-xs text-gray-500"><b>Total Units:</b></td>
                        <td class="px-3 py-2 text-sm"><b>{{ $totalUnits }}</b></td>
                        <td colspan="5"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @endif
    </div>

    <p class="text-[11px] text-gray-400 text-center">
        © {{ date('Y') }} SchedSmart. All rights reserved.
    </p>
</div>
