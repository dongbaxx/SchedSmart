<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Edit Subject</h1>
            <p class="text-sm text-gray-500">Update subject details</p>
        </div>

        <a href="{{ route('head.subjects.index') }}"
           wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border hover:bg-gray-50">
            ← Back
        </a>
    </div>

    @if (session('success'))
        <div class="mt-4 rounded-lg bg-green-50 text-green-800 px-4 py-2">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white rounded-2xl border p-6 space-y-6">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm">Course Code <span class="text-red-600">*</span></label>
                <input type="text" wire:model.defer="course_code" class="w-full border rounded-xl px-3 py-2">
                @error('course_code') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Descriptive Title <span class="text-red-600">*</span></label>
                <input type="text" wire:model.defer="descriptive_title" class="w-full border rounded-xl px-3 py-2">
                @error('descriptive_title') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Units</label>
                <input type="number" wire:model.defer="units" class="w-full border rounded-xl px-3 py-2">
                @error('units') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-sm">LEC</label>
                    <input type="number" wire:model.defer="lec" class="w-full border rounded-xl px-3 py-2">
                    @error('lec') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">LAB</label>
                    <input type="number" wire:model.defer="lab" class="w-full border rounded-xl px-3 py-2">
                    @error('lab') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">CMO</label>
                    <input type="number" wire:model.defer="cmo" class="w-full border rounded-xl px-3 py-2">
                    @error('cmo') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="text-sm">HEI</label>
                <input type="number" wire:model.defer="hei" class="w-full border rounded-xl px-3 py-2">
                @error('hei') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Prerequisite</label>
                <input type="text" wire:model.defer="pre_requisite" class="w-full border rounded-xl px-3 py-2" placeholder="None">
                @error('pre_requisite') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Year Level <span class="text-red-600">*</span></label>
                <select wire:model.defer="year_level" class="w-full border rounded-xl px-3 py-2">
                    <option value="">Select year…</option>
                    @foreach($yearLevels as $yl)
                        <option value="{{ $yl }}">{{ $yl }}</option>
                    @endforeach
                </select>
                @error('year_level') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Specialization (optional)</label>
                <select wire:model.defer="specialization_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="">None</option>
                    @foreach($specializations as $sp)
                        <option value="{{ (string)$sp->id }}">{{ $sp->name }}</option>
                    @endforeach
                </select>
                @error('specialization_id') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Effectivity Year</label>
                <input type="text" wire:model.defer="efectivity_year" class="w-full border rounded-xl px-3 py-2" placeholder="e.g., AY 2025-2026">
                @error('efectivity_year') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Semester</label>
                <select wire:model.defer="semester" class="w-full border rounded-xl px-3 py-2">
                    <option value="">Select semester…</option>
                    @foreach($semesters as $sm)
                        <option value="{{ $sm }}">{{ $sm }}</option>
                    @endforeach
                </select>
                @error('semester') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('head.subjects.index') }}"
               wire:navigate
               class="px-4 py-2 rounded-xl border hover:bg-gray-50">Cancel</a>

            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
                Save Changes
            </button>
        </div>
    </form>
</div>
