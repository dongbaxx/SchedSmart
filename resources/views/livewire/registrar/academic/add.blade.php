<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Add Academic Term</h1>
        <a href="{{ route('registrar.academic.index') }}"
           class="px-3 py-2 rounded-lg border hover:bg-gray-50 text-sm">← Back</a>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="bg-white rounded-lg border p-5 space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">School Year</label>
            <input type="text" wire:model="school_year"
                   class="w-full rounded-lg border px-3 py-2 text-sm"
                   placeholder="e.g., 2025-2026">
            @error('school_year') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Semester</label>
            <select wire:model="semester" class="w-full rounded-lg border px-3 py-2 text-sm">
                <option value="">Select semester…</option>
                <option>1st Semester</option>
                <option>2nd Semester</option>
                <option>Summer</option>
            </select>
            @error('semester') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm">
                Save Term
            </button>
        </div>
    </form>
</div>
