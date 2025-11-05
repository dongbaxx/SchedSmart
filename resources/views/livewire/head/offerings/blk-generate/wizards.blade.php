<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Bulk Generate Offerings</h1>
        <a href="{{ route('head.offerings.index') }}"
           class="inline-flex items-center rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-800 px-3 py-2 text-sm">
            Back to Offerings
        </a>
    </div>

    {{-- Flash --}}
    @if (session('error'))
        <div class="rounded-md bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if (session('success'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stepper --}}
    <div class="flex items-center gap-3 text-sm">
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full {{ $step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center">1</div>
            <span class="{{ $step >= 1 ? 'text-gray-900' : 'text-gray-500' }}">Choose Term & Cohort</span>
        </div>
        <div class="h-px flex-1 bg-gray-200"></div>
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full {{ $step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center">2</div>
            <span class="{{ $step >= 2 ? 'text-gray-900' : 'text-gray-500' }}">Preview & Sections</span>
        </div>
        <div class="h-px flex-1 bg-gray-200"></div>
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full {{ $step >= 3 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }} flex items-center justify-center">3</div>
            <span class="{{ $step >= 3 ? 'text-gray-900' : 'text-gray-500' }}">Generate</span>
        </div>
    </div>

    {{-- Step 1 --}}
    @if ($step === 1)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
           <div>
                <label class="block text-sm font-medium text-gray-700">Academic Term</label>
                <input type="text" value="{{ $term_sy }} — {{ $term_semester }}"
                    class="mt-1 w-full rounded-lg border-gray-200 bg-gray-50" readonly>
            </div>


            {{-- Course is auto from Head; just show a read-only preview --}}
            <div>
                <label class="block text-sm font-medium text-gray-700">Course</label>
                <input type="text" value="{{ $courseName ?? '—' }}" class="mt-1 w-full rounded-lg border-gray-200 bg-gray-50" readonly>
            </div>

            {{-- Year Level (dropdown that matches DB text) --}}
            <div>
                {{-- Year Level --}}
                <label class="text-sm font-medium">Year Level</label>
                <select class="form-select" wire:model="year_level">
                    <option value="">-- Select Year Level --</option>
                    <option value="1">First Year</option>
                    <option value="2">Second Year</option>
                    <option value="3">Third Year</option>
                    <option value="4">Fourth Year</option>
                </select>
                @error('year_level') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror

            </div>

            <div class="md:col-span-3">
                <label class="block text-sm font-medium text-gray-700">Effectivity Year (optional)</label>
                <input type="text" wire:model.defer="effectivity_year" placeholder="e.g., 2024-2025 or 2024"
                       class="mt-1 w-full rounded-lg border-gray-300">
                <p class="mt-1 text-xs text-gray-500">
                    Defaults to the academic year above if left blank.
                </p>
                @error('effectivity_year') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <button wire:click="next"
                    class="inline-flex items-center rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm"
                    @disabled(! $courseName)>
                Next
            </button>
        </div>
    @endif

    {{-- Step 2 --}}
    @if ($step === 2)
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Suggested Subjects (Curricula) --}}
            <div>
                <h3 class="font-semibold mb-2">Suggested Subjects (Curricula)</h3>
                <div class="border rounded divide-y">
                    @forelse($subjects as $s)
                        <div class="p-2">
                            <div class="font-medium">{{ $s['course_code'] }} — {{ $s['title'] }}</div>
                            <div class="text-xs text-gray-500">Units: {{ $s['units'] ?? '—' }}</div>
                        </div>
                    @empty
                        <div class="p-3 text-gray-500 text-sm">
                            No matching subjects for the chosen term/year.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Sections --}}
            <div>
                <h3 class="font-semibold mb-2">Sections (choose where to create offerings)</h3>
                <div class="border rounded divide-y">
                    @forelse($sections as $sec)
                        <label class="p-2 flex items-center gap-3">
                            <input type="checkbox"
                                   wire:model="sectionChecks.{{ $sec->id }}"
                                   class="rounded border-gray-300">
                            <div>
                                <div class="font-medium">{{ $sec->section_name }}</div>
                                <div class="text-xs text-gray-500">Year Level: {{ $sec->year_level }}</div>
                            </div>
                        </label>
                    @empty
                        <div class="p-3 text-gray-500 text-sm">
                            No sections for your course and selected year level.
                        </div>
                    @endforelse
                </div>
                @error('sectionChecks') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button wire:click="back"
                    class="inline-flex items-center rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 text-sm">
                Back
            </button>
            <button wire:click="next"
                    class="inline-flex items-center rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm">
                Continue
            </button>
        </div>
    @endif

    {{-- Step 3 (Confirm) --}}
    @if ($step === 3)
        <div class="rounded-lg border p-4">
            <h3 class="font-semibold mb-2">Confirm Generation</h3>
            <dl class="text-sm grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2">
                <div>
                    <dt class="text-gray-500">Academic Term</dt>
                    <dd class="font-medium">
                        {{ $term_sy }} — {{ $term_semester }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Effectivity Year</dt>
                    <dd class="font-medium">{{ $effectivity_year }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Course</dt>
                    <dd class="font-medium">
                        {{ $courseName ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500">Year Level</dt>
                    <dd class="font-medium">{{ $year_level }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-gray-500">Sections</dt>
                    <dd class="font-medium">
                        @php
                            $chosen = collect($sectionChecks)
                                ->filter()
                                ->keys()
                                ->map(fn($id) => optional($sections->firstWhere('id', (int)$id))->section_name)
                                ->filter()
                                ->values()
                                ->implode(', ');
                        @endphp
                        {{ $chosen ?: '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="flex items-center justify-between">
            <button wire:click="back"
                    class="inline-flex items-center rounded-lg border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 text-sm">
                Back
            </button>
            <button wire:click="generate"
                    class="inline-flex items-center rounded-lg bg-green-600 hover:bg-green-700 text-white px-4 py-2 text-sm">
                Generate Offerings
            </button>
        </div>
    @endif
</div>
