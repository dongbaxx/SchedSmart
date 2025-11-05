<div class="p-6 space-y-4">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Academic Terms</h1>
        <a href="{{ route('registrar.academic.add') }}" 
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
           + Add Academic Term
        </a>
    </div>

    @if (session()->has('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 text-red-800 p-3 rounded">
            {{ session('error') }}
        </div>
    @endif

    <div class="mt-2">
        <input type="text" wire:model.live="search" 
               placeholder="Search academic year or semester..."
               class="border-gray-300 rounded-lg p-2 w-full dark:bg-gray-800 dark:text-gray-200">
    </div>

    <div class="overflow-x-auto mt-4">
        <table class="min-w-full bg-white dark:bg-gray-900 rounded-lg overflow-hidden shadow">
            <thead class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-100">
                <tr>
                    <th class="py-2 px-4 text-left">#</th>
                    <th class="py-2 px-4 text-left">School Year</th>
                    <th class="py-2 px-4 text-left">Semester</th>
                    <th class="py-2 px-4 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($academic_years as $index => $academic)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="py-2 px-4">{{ $index + 1 }}</td>
                        <td class="py-2 px-4">{{ $academic->school_year }}</td>
                        <td class="py-2 px-4 capitalize">{{ $academic->semester }}</td>
                        <td class="py-2 px-4 text-center">
                            <a href="{{ route('registrar.academic.edit', $academic) }}"
                            wire:navigate
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border">
                                <!-- Pencil Square icon -->
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-5 h-5 text-blue-600"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414a2 2 0 01.586-1.414z" />
                                </svg>

                            </a>

                            <button
                                x-data
                                @click="if (confirm('Delete this room permanently?')) { $wire.delete({{ $academic->id }}) }"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-500 text-red-600 hover:bg-red-50 text-sm">
                                <!-- Trash/Delete icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                </svg>
                            </button>

                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center py-4 text-gray-500">
                            No academic terms found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
