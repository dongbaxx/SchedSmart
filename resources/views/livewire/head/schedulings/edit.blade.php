<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Edit Time &amp; Room</h1>
            <p class="text-xs text-gray-500">
                Section: <b>{{ $offering->section?->section_name ?? '—' }}</b>
                • Year Level: <b>{{ $offering->year_level ?? '—' }}</b>
                • A.Y.: <b>{{ $offering->academic?->school_year ?? '—' }}</b>
                • Semester: <b>{{ $offering->academic?->semester ?? '—' }}</b>
            </p>
        </div>

        <a href="{{ route('head.schedulings.editor', $offering) }}"
           class="px-3 py-2 rounded-lg border text-sm hover:bg-gray-50">
            Back to Planner
        </a>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-3 py-2 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 px-3 py-2 text-sm">
            {{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 border border-red-200 text-red-800 px-3 py-2 text-sm">
            Please fix the highlighted fields before saving.
        </div>
    @endif

    <div class="rounded-xl border p-4 space-y-3">
        <h2 class="font-semibold text-sm">Generated Meetings</h2>

        @if(empty($rows))
            <p class="text-sm text-gray-500">No meetings found for this section.</p>
        @else
            <div class="overflow-x-auto border rounded-xl">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">Subject</th>
                        <th class="px-3 py-2 text-left">Faculty</th>
                        <th class="px-3 py-2 text-left">Day</th>
                        <th class="px-3 py-2 text-left">Start</th>
                        <th class="px-3 py-2 text-left">End</th>
                        <th class="px-3 py-2 text-left">Room</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y">
                    @foreach($rows as $i => $row)
                        <tr wire:key="edit-row-{{ implode('-', $row['meeting_ids']) }}">
                            {{-- Subject --}}
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium">
                                    {{ $row['code'] }} — {{ $row['title'] }}
                                </div>
                            </td>

                            {{-- Faculty --}}
                            <td class="px-3 py-2 align-top">
                                {{ $row['faculty'] ?: '—' }}
                            </td>

                            {{-- Day --}}
                            <td class="px-3 py-2 align-top">
                                <div class="flex items-center gap-2">
                                    <select wire:model="rows.{{ $i }}.day"
                                            class="border-gray-300 rounded-md text-xs">
                                        <option value="">—</option>
                                        <option value="MON">MON</option>
                                        <option value="TUE">TUE</option>
                                        <option value="WED">WED</option>
                                        <option value="THU">THU</option>
                                        <option value="FRI">FRI</option>
                                        <option value="SAT">SAT</option>
                                    </select>
                                    @if($row['is_pair'])
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">
                                            pair (auto second day)
                                        </span>
                                    @endif
                                </div>
                                @error("rows.$i.day")
                                    <div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>
                                @enderror
                            </td>

                            {{-- Start time --}}
                            <td class="px-3 py-2 align-top">
                                <input type="time"
                                       wire:model="rows.{{ $i }}.start_time"
                                       class="border-gray-300 rounded-md text-xs">
                                @error("rows.$i.start_time")
                                    <div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>
                                @enderror
                            </td>

                            {{-- End time --}}
                            <td class="px-3 py-2 align-top">
                                <input type="time"
                                       wire:model="rows.{{ $i }}.end_time"
                                       class="border-gray-300 rounded-md text-xs">
                                @error("rows.$i.end_time")
                                    <div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>
                                @enderror
                            </td>

                            {{-- Room --}}
                            <td class="px-3 py-2 align-top">
                                <div class="flex flex-col gap-1">
                                    <select wire:model="rows.{{ $i }}.room_id"
                                            class="border-gray-300 rounded-md text-xs">
                                        <option value="">—</option>
                                        @foreach($rooms as $room)
                                            <option value="{{ $room->id }}">{{ $room->code }}</option>
                                        @endforeach
                                    </select>

                                    @error("rows.$i.room_id")
                                        <div class="text-xs text-red-600 mt-0.5">{{ $message }}</div>
                                    @enderror
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end pt-3">
                <button type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="px-4 py-2 rounded-lg text-sm bg-indigo-600 text-white hover:bg-indigo-700">
                    <span wire:loading.remove wire:target="save">Save Changes</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </div>
        @endif
    </div>

    <p class="text-[11px] text-gray-400 text-center">
        © {{ date('Y') }} SchedSmart. All rights reserved.
    </p>
</div>
