<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Course Offering — Planner</h1>
            <p class="text-xs text-gray-500">
                Section: <b>{{ $offering->section?->section_name ?? '—' }}</b>
                • Year Level: <b>{{ $offering->year_level ?? '—' }}</b>
                • A.Y.: <b>{{ $offering->academic?->school_year ?? '—' }}</b>
                • Semester: <b>{{ $offering->academic?->semester ?? '—' }}</b>
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

    {{-- Flash --}}
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

    {{-- Single Planner Table (no other tables) --}}
    <div class="rounded-xl border p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">
                Subjects ({{ $offering->year_level ?? '—' }})
                <span class="ml-2 text-xs text-gray-500">({{ !empty($plan) ? count($plan) : 0 }} total)</span>
            </h2>
        </div>

        @if(!$planLoaded)
            <p class="text-sm text-gray-500">Loading subjects…</p>
        @elseif(empty($plan))
            <p class="text-sm text-gray-500">No suggested subjects found for this section.</p>
        @else
            <div class="overflow-hidden border rounded-xl">
                <table class="w-full text-sm">
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
                    <tbody>
                    @foreach($plan as $i => $row)
                        @php
                            $f = ($row['faculty_id'] ?? null) ? $faculty->firstWhere('id',$row['faculty_id']) : null;
                            $r = ($row['room_id'] ?? null) ? $rooms->firstWhere('id',$row['room_id']) : null;
                        @endphp
                        <tr class="border-t" wire:key="plan-row-{{ $row['curriculum_id'] }}-{{ $i }}">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $row['code'] }} — {{ $row['title'] }}</div>
                                <div class="text-xs text-gray-500">
                                    Spec: {{ $row['specialization'] ?? '—' }} • Room Type: {{ $row['room_type_label'] ?? '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ $row['type'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['units'] ?? '—' }}</td>
                            <td class="px-3 py-2">
                                {{ $row['inc'] ? 'INC' : ($f?->name ?? '—') }}
                            </td>
                            <td class="px-3 py-2">{{ $r?->code ?? '—' }}</td>
                            <td class="px-3 py-2">
                                {{ $row['day'] ?? '—' }}
                                @if(!empty($row['day']) && str_contains($row['day'], '/'))
                                    <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">pair</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $row['start_time'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $row['end_time'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-gray-50">
                            <td class="px-3 py-2 text-right text-xs text-gray-500" colspan="2"><b>Total Units:</b></td>
                            <td class="px-3 py-2 text-sm"><b>{{ $totalUnits }}</b></td>
                            <td class="px-3 py-2" colspan="5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-gray-500">
                    Even without rooms, the planner assigns instructors, days, and time. Heads get a one-time admin load; faculty loads are saved per subject/section/term.
                    @if($alreadyGenerated && $hasUnassigned)
                        <span class="ml-2 text-amber-600">
                            Incomplete: {{ $incompleteCount }} subject{{ $incompleteCount === 1 ? '' : 's' }} have no assigned faculty.
                        </span>
                    @endif
                </p>

                <div class="flex items-center gap-2">
                    @if($alreadyGenerated && $hasUnassigned)
                        <button type="button"
                                wire:click="cancelSection"
                                wire:loading.attr="disabled"
                                wire:target="cancelSection"
                                class="px-3 py-2 rounded-lg text-sm bg-red-50 text-red-700 hover:bg-red-100 border border-red-200">
                            <span wire:loading.remove wire:target="cancelSection">Cancel Section</span>
                            <span wire:loading wire:target="cancelSection">Canceling…</span>
                        </button>
                    @endif

                    @if($alreadyGenerated)
                        <button type="button"
                                wire:click="regenerateSection"
                                wire:loading.attr="disabled"
                                wire:target="regenerateSection"
                                class="px-3 py-2 rounded-lg text-sm bg-amber-600 text-white hover:bg-amber-700">
                            <span wire:loading.remove wire:target="regenerateSection">Regenerate</span>
                            <span wire:loading wire:target="regenerateSection">Regenerating…</span>
                        </button>
                    @endif

                    <button type="button"
                            @disabled($alreadyGenerated)
                            wire:click="generate"
                            wire:loading.attr="disabled"
                            wire:target="generate"
                            class="px-3 py-2 rounded-lg text-sm {{ $alreadyGenerated ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                        <span wire:loading.remove wire:target="generate">
                            {{ $alreadyGenerated ? 'Generated' : 'Generate' }}
                        </span>
                        <span wire:loading wire:target="generate">Generating…</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <p class="text-[11px] text-gray-400 text-center">© {{ date('Y') }} SchedSmart. All rights reserved.</p>
</div>
