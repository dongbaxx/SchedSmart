<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Add Curriculum</h1>
            <p class="text-sm text-gray-500">Create a new curriculum record</p>
        </div>

        {{-- Back to index --}}
        <a
            href="{{ route('registrar.curricula.index') }}"
            wire:navigate
            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border hover:bg-gray-50"
            aria-label="Back to Curricula"
            title="Back to Curricula"
        >
            <!-- Back icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    {{-- Success flash (in case you redirect back here later) --}}
    @if (session('ok'))
        <div class="px-4 py-2 rounded-lg border bg-green-50 text-green-800">
            {{ session('ok') }}
        </div>
    @endif

    <!-- Form -->
    <form wire:submit.prevent="save" class="bg-white rounded-2xl border p-6 space-y-6">
        <div class="grid md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm">Course Code <span class="text-red-600">*</span></label>
                <input type="text" wire:model.defer="form.course_code" class="w-full border rounded-xl px-3 py-2">
                @error('form.course_code') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm">Descriptive Title <span class="text-red-600">*</span></label>
                <input type="text" wire:model.defer="form.descriptive_title" class="w-full border rounded-xl px-3 py-2">
                @error('form.descriptive_title') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Units</label>
                <input type="number" wire:model.defer="form.units" class="w-full border rounded-xl px-3 py-2">
                @error('form.units') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-sm">LEC</label>
                    <input type="number" wire:model.defer="form.lec" class="w-full border rounded-xl px-3 py-2">
                    @error('form.lec') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">LAB</label>
                    <input type="number" wire:model.defer="form.lab" class="w-full border rounded-xl px-3 py-2">
                    @error('form.lab') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="text-sm">CMO</label>
                    <input type="number" wire:model.defer="form.cmo" class="w-full border rounded-xl px-3 py-2">
                    @error('form.cmo') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="text-sm">HEI</label>
                <input type="number" wire:model.defer="form.hei" class="w-full border rounded-xl px-3 py-2">
                @error('form.hei') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm">Prerequisite</label>
                <input type="text" wire:model.defer="form.pre_requisite" class="w-full border rounded-xl px-3 py-2" placeholder="None">
                @error('form.pre_requisite') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Course <span class="text-red-600">*</span></label>
                <select wire:model.defer="form.course_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="">Select course…</option>
                    @foreach($courses as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                @error('form.course_id') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm">Year Level <span class="text-red-600">*</span></label>
                <select wire:model.defer="form.year_level" class="w-full border rounded-xl px-3 py-2">
                    <option value="">Select year…</option>
                    @foreach($yearLevels as $yl)
                        <option value="{{ $yl }}">{{ $yl }}</option>
                    @endforeach
                </select>
                @error('form.year_level') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Specialization</label>
                <select wire:model.defer="form.specialization_id" class="w-full border rounded-xl px-3 py-2">
                    <option value="">None</option>
                    @foreach($specializations as $sp)
                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                    @endforeach
                </select>
                @error('form.specialization_id') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-sm">Effectivity Year</label>
                <input type="text" wire:model.defer="form.efectivity_year" class="w-full border rounded-xl px-3 py-2" placeholder="e.g., AY 2025-2026">
                @error('form.efectivity_year') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>

            <div>
                <label class="text-sm">Semester</label>
                <select wire:model.defer="form.semester" class="w-full border rounded-xl px-3 py-2">
                    <option value="">Select semester…</option>
                    @foreach($semesters as $sm)
                        <option value="{{ $sm }}">{{ $sm }}</option>
                    @endforeach
                </select>
                @error('form.semester') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <a
                href="{{ route('registrar.curricula.index') }}"
                wire:navigate
                class="px-4 py-2 rounded-xl border hover:bg-gray-50"
            >Cancel</a>
            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
                Save Curriculum
            </button>
        </div>
    </form>
</div>
