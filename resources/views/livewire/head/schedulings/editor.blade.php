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
            @php
                $hasAnyInc = collect($plan)->where('inc', true)->isNotEmpty();
            @endphp

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
                        @if($hasAnyInc)
                            <th class="px-3 py-2 text-left w-64">Why?</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y">
                    @foreach($plan as $i => $row)
                        @php
                            $f = ($row['faculty_id'] ?? null) ? $faculty->firstWhere('id',$row['faculty_id']) : null;
                            $r = ($row['room_id'] ?? null) ? $rooms->firstWhere('id',$row['room_id']) : null;
                        @endphp
                        <tr wire:key="plan-row-{{ $row['curriculum_id'] }}-{{ $i }}">
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium flex items-center gap-2">
                                    <span>{{ $row['code'] }} — {{ $row['title'] }}</span>
                                    @if($row['inc'])
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-red-100 text-red-700">INC</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500">
                                    Spec: {{ $row['specialization'] ?? '—' }} • Room Type: {{ $row['room_type_label'] ?? '—' }}
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top">{{ $row['type'] ?? '—' }}</td>
                            <td class="px-3 py-2 align-top">{{ $row['units'] ?? '—' }}</td>
                            <td class="px-3 py-2 align-top">
                                @if($row['inc'])
                                    <span class="text-gray-400">—</span>
                                @else
                                    {{ $f?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                @if($row['field'] ?? false)
                                    Field
                                @else
                                    {{ $r?->code ?? '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">
                                {{ $row['day'] ?? '—' }}
                                @if(!empty($row['day']) && str_contains($row['day'], '/'))
                                    <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">pair</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 align-top">{{ $row['start_time'] ?? '—' }}</td>
                            <td class="px-3 py-2 align-top">{{ $row['end_time'] ?? '—' }}</td>

                            @if($hasAnyInc)
                                <td class="px-3 py-2 align-top">
                                    @if($row['inc'])
                                        <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded px-2 py-1">
                                            {{ $row['inc_reason'] ?? 'No eligible faculty' }}
                                        </div>
                                    @else
                                        <span class="text-gray-300 text-xs">—</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-gray-50">
                            <td class="px-3 py-2 text-right text-xs text-gray-500" colspan="2"><b>Total Units:</b></td>
                            <td class="px-3 py-2 text-sm"><b>{{ $totalUnits }}</b></td>
                            <td class="px-3 py-2" colspan="{{ $hasAnyInc ? 6 : 5 }}"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Footer actions & status --}}
            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-gray-500">
                    Even without rooms, the planner assigns instructors, days, and time. Heads get a one-time admin load; faculty loads are saved per subject/section/term.
                    @if($alreadyGenerated)
                        @if($hasUnassigned)
                            <span class="ml-2 inline-flex items-center text-[11px] px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                                Incomplete: {{ $incompleteCount }}
                            </span>
                        @else
                            <span class="ml-2 inline-flex items-center text-[11px] px-2 py-0.5 rounded bg-green-100 text-green-800">
                                Complete
                            </span>
                        @endif
                    @endif
                </p>

                <div class="flex items-center gap-2">
                    @php
                        $isIncomplete = $alreadyGenerated && $hasUnassigned;
                        $isComplete   = $alreadyGenerated && !$hasUnassigned;
                        $notGenerated = !$alreadyGenerated;
                    @endphp

                    {{-- NEW: Edit Time & Room (separate page) --}}
                    @if($alreadyGenerated)
                        <a href="{{ route('head.schedulings.edit', $offering) }}"
                           class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                            Edit Time &amp; Room
                        </a>
                    @endif

                    {{-- INCOMPLETE: View + Regenerate + Cancel (hide Generate) --}}
                    @if($isIncomplete)
                        <button type="button"
                                wire:click="viewSection"
                                wire:loading.attr="disabled"
                                wire:target="viewSection"
                                class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                            <span wire:loading.remove wire:target="viewSection">View</span>
                            <span wire:loading wire:target="viewSection">Opening…</span>
                        </button>

                        <button type="button"
                                wire:click="regenerateSection"
                                wire:loading.attr="disabled"
                                wire:target="regenerateSection"
                                class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                            <span wire:loading.remove wire:target="regenerateSection">Regenerate</span>
                            <span wire:loading wire:target="regenerateSection">Regenerating…</span>
                        </button>

                        <button type="button"
                                wire:click="cancelSection"
                                wire:loading.attr="disabled"
                                wire:target="cancelSection"
                                class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
                            <span wire:loading.remove wire:target="cancelSection">Cancel</span>
                            <span wire:loading wire:target="cancelSection">Cancelling…</span>
                        </button>
                    @endif

                    {{-- NOT GENERATED: show Generate --}}
                    @if($notGenerated)
                        <button type="button"
                                wire:click="generate"
                                wire:loading.attr="disabled"
                                wire:target="generate"
                                class="px-3 py-2 rounded-lg text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                            <span wire:loading.remove wire:target="generate">Generate</span>
                            <span wire:loading wire:target="generate">Generating…</span>
                        </button>
                    @endif

                    {{-- COMPLETE: single disabled “Generated” --}}
                    @if($isComplete)
                        <button type="button"
                                disabled
                                class="px-3 py-2 rounded-lg text-sm bg-gray-200 text-gray-500 cursor-not-allowed">
                            Generated
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <p class="text-[11px] text-gray-400 text-center">© {{ date('Y') }} SchedSmart. All rights reserved.</p>
</div>
