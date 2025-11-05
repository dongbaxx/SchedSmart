<div class="p-6 space-y-4">
    <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-4">Edit Academic Term</h1>

    @if (session()->has('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="update" class="space-y-4 max-w-lg">
        <div>
            <label class="block mb-1 font-semibold">School Year</label>
            <input type="text" wire:model="school_year"
                   class="w-full border-gray-300 rounded-lg p-2 dark:bg-gray-800 dark:text-gray-200"
                   placeholder="e.g. 2025-2026">
            @error('school_year') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block mb-1 font-semibold">Semester</label>
            <select wire:model="semester"
                    class="w-full border-gray-300 rounded-lg p-2 dark:bg-gray-800 dark:text-gray-200">
                <option value="">Select Semester</option>
                <option value="1st Semester">1st Semester</option>
                <option value="2nd Semester">2nd Semester</option>
                <option value="Summer">Summer</option>
            </select>
            @error('semester') <p class="text-red-500 text-sm">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                Update
            </button>

            <a href="{{ route('registrar.academic.index') }}"
               class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
               Cancel
            </a>
        </div>
    </form>
</div>
