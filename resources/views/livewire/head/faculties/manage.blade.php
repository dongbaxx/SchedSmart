<div class="w-full px-6 lg:px-10 py-6 space-y-6">

    {{-- TOP BAR --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Manage Faculty</h1>
            <p class="text-sm text-gray-500">Employment & availability setup</p>
        </div>

        <a href="{{ route('head.faculties.index') }}"
           class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
            ← Back
        </a>
    </div>

    {{-- FULL SCREEN GRID --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

        {{-- ================= LEFT MAIN ================= --}}
        <div class="xl:col-span-8 space-y-6">

            {{-- FACULTY INFO (SIMPLE) --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ $user->name }}</div>
                        <div class="text-sm text-gray-500">{{ $user->email }}</div>
                    </div>

                    <div class="text-sm text-gray-600">
                        <div><span class="text-gray-500">Department:</span> {{ $user->department?->department_name ?? '—' }}</div>
                        <div><span class="text-gray-500">Course:</span> {{ $user->course?->course_name ?? '—' }}</div>
                        <div><span class="text-gray-500">Code:</span> {{ $user->userDepartment?->user_code_id ?? '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- ✅ STEP 0: USERS DEPARTMENT RECORD (NEW) --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-semibold">0</span>
                        <h2 class="text-base font-semibold text-gray-900">Users Department Record</h2>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Department is locked to Head’s department. You can update user code & position.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User Code ID</label>
                        <input type="text" wire:model.defer="user_code_id"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                        @error('user_code_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                        <select wire:model.defer="position"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="Faculty">Faculty</option>
                            <option value="Head">Head</option>
                            <option value="Dean">Dean</option>
                        </select>
                        @error('position') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department (locked)</label>
                        <input type="text" disabled
                               value="{{ $user->department?->department_name ?? '—' }}"
                               class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button wire:click="saveDepartmentRecord"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">
                        Save Users Department
                    </button>

                    @if (session('success_department'))
                        <span class="text-sm text-emerald-700">{{ session('success_department') }}</span>
                    @endif
                </div>
            </div>

            {{-- STEP 1: EMPLOYMENT --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-semibold">1</span>
                        <h2 class="text-base font-semibold text-gray-900">Employment</h2>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Choose status first. If <b>Full-Time</b>, schedule will be saved automatically.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Classification</label>
                        <select wire:model.defer="employment_classification"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="Teaching">Teaching</option>
                            <option value="Non-Teaching">Non-Teaching</option>
                        </select>
                        @error('employment_classification') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select wire:model.live="employment_status"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                            <option value="Full-Time">Full-Time</option>
                            <option value="Part-Time">Part-Time</option>
                        </select>
                        @error('employment_status') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Regular Load</label>
                        <input type="number" wire:model.defer="regular_load"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                        @error('regular_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Extra Load</label>
                        <input type="number" wire:model.defer="extra_load"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
                        @error('extra_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button wire:click="saveEmployment"
                            class="rounded-lg bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700">
                        Save Employment
                    </button>

                    @if (session('success_employment'))
                        <span class="text-sm text-emerald-700">{{ session('success_employment') }}</span>
                    @endif
                </div>

                {{-- FULL-TIME NOTICE (VERY CLEAR) --}}
                @if (!$this->isPartTime)
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                        <div class="text-sm font-semibold text-emerald-800">Full-Time Auto Schedule</div>
                        <div class="text-sm text-emerald-700">
                            Automatically saved as:
                            <b>MON–FRI</b> • <b>07:30 AM – 06:00 PM</b><br>
                            No manual input needed.
                        </div>
                        @if (session('success_availability'))
                            <div class="text-sm text-emerald-700 mt-2">{{ session('success_availability') }}</div>
                        @endif
                    </div>
                @endif
            </div>

        </div>

        {{-- ================= RIGHT SIDEBAR ================= --}}
        <div class="xl:col-span-4">
            <div class="xl:sticky xl:top-6">

                {{-- STEP 2: AVAILABILITY (ONLY IF PART-TIME) --}}
                @if ($this->isPartTime)
                    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">

                        <div class="mb-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white text-[10px] font-semibold">2</span>
                                <h2 class="text-sm font-semibold text-gray-900">Part-Time Availability</h2>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Turn on the day then choose start & end time.
                            </p>
                        </div>

                        <div class="max-h-[420px] overflow-y-auto pr-1 space-y-2">

                            @foreach ($days as $day)
                                <div class="rounded-lg border border-gray-200 p-2">

                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <div class="w-10 text-xs font-semibold text-gray-900">{{ $day }}</div>

                                            <label class="inline-flex items-center gap-1.5 text-xs text-gray-700">
                                                <input type="checkbox"
                                                    wire:model.live="dayEnabled.{{ $day }}"
                                                    class="h-3.5 w-3.5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                Available
                                            </label>
                                        </div>

                                        @if ($day === 'FRI')
                                            <div class="text-[10px] text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2 py-1">
                                                Fri: 13:00–16:00, 14:30–17:30, 16:00–19:00, 17:30–20:30
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-[10px] font-medium text-gray-500 mb-1">Start</label>
                                            <select wire:model.defer="dayStart.{{ $day }}"
                                                    @disabled(!($dayEnabled[$day] ?? false))
                                                    class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                                                @foreach($gridStartOptions as $t)
                                                    <option value="{{ $t }}">{{ $t }}</option>
                                                @endforeach
                                            </select>
                                            @error("dayStart.$day")
                                                <div class="mt-1 text-[10px] text-red-600">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div>
                                            <label class="block text-[10px] font-medium text-gray-500 mb-1">End</label>
                                            <select wire:model.defer="dayEnd.{{ $day }}"
                                                    @disabled(!($dayEnabled[$day] ?? false))
                                                    class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs focus:ring-1 focus:ring-emerald-500 disabled:bg-gray-100">
                                                @foreach($gridEndOptions as $t)
                                                    <option value="{{ $t }}">{{ $t }}</option>
                                                @endforeach
                                            </select>
                                            @error("dayEnd.$day")
                                                <div class="mt-1 text-[10px] text-red-600">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                </div>
                            @endforeach

                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <button wire:click="saveAvailability"
                                    class="rounded-md bg-emerald-600 px-3 py-2 text-xs text-white hover:bg-emerald-700">
                                Save Availability
                            </button>

                            @if (session('success_availability'))
                                <span class="text-xs text-emerald-700">{{ session('success_availability') }}</span>
                            @endif
                        </div>
                    </div>
                @endif

            </div>
        </div>

    </div>
</div>
