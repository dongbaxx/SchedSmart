<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Edit Course Offering</h1>
        <a href="{{ route('registrar.offering.index') }}"
           wire:navigate
           class="px-4 py-2 rounded-xl border hover:bg-gray-50">← Back</a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-800 px-4 py-2 mt-4">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white rounded-xl border p-5 space-y-4">
        <div class="grid md:grid-cols-2 gap-4">
            {{-- Academic Term --}}
            <div>
                <label class="block text-sm font-medium mb-1">Academic Term</label>
                <select wire:model="academic_id" class="w-full rounded-lg border px-3 py-2">
                    <option value="">Select academic year…</option>
                    @foreach($academics as $ay)
                        @php
                            $sem = $ay->semester_name ?? $ay->semester ?? null;
                        @endphp
                        <option value="{{ $ay->id }}">
                            {{ $ay->school_year }}{{ $sem ? ' — '.$sem : '' }}
                        </option>
                    @endforeach
                </select>
                @error('academic_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror

            </div>

            {{-- Program/Course --}}
            <div>
                <label class="block text-sm font-medium mb-1">Program/Course</label>
                <select wire:model="course_id" class="w-full rounded-lg border px-3 py-2 text-sm">
                    <option value="">Select course</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->course_name }}</option>
                    @endforeach
                </select>
                @error('course_id') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Year Level --}}
            <div>
                <label class="block text-sm font-medium mb-1">Year Level</label>
                <select wire:model="year_level" class="w-full rounded-lg border px-3 py-2 text-sm">
                    <option value="">Select level</option>
                    @foreach ($levels as $lvl)
                        <option value="{{ $lvl }}">{{ $lvl }}</option>
                    @endforeach
                </select>
                @error('year_level') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Section (filtered by course + year) --}}
            <div>
                <label class="block text-sm font-medium mb-1">Section</label>
                <select wire:model="section_id" class="w-full rounded-lg border px-3 py-2 text-sm" @disabled(!$sections->count())>
                    <option value="">{{ $sections->count() ? 'Select section' : 'Select course & year first' }}</option>
                    @foreach ($sections as $sec)
                        <option value="{{ $sec->id }}">{{ $sec->section_name }}</option>
                    @endforeach
                </select>
                @error('section_id') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
            </div>

            {{-- Effectivity Year (optional) --}}
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Effectivity Year (optional)</label>
                <input type="text" wire:model.defer="effectivity_year"
                       class="w-full rounded-lg border px-3 py-2 text-sm"
                       placeholder="e.g., 2025-2026">
                @error('effectivity_year') <div class="text-red-600 text-xs mt-1">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="inline-flex items-center rounded-lg bg-emerald-600 text-white px-4 py-2 text-sm hover:bg-emerald-700">
                Save Changes
            </button>
        </div>
    </form>
</div>
