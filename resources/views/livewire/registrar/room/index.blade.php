<div class="p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-end gap-4 mt-4">
        <!-- Search box -->
        <input
            type="text"
            wire:model.live="search"
            placeholder="Search rooms..."
            class="w-64 rounded-lg border px-2 py-2"
        >

        <!-- Add Room button -->
        <a href="{{ route('registrar.room.form') }}"
           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
            Add Room
        </a>
    </div>

    <!-- Flash -->
    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-700 px-3 py-2">
            {{ session('success') }}
        </div>
    @endif

    <!-- Table -->
    <div class="overflow-x-auto mt-4">
        <table class="w-full border-collapse border border-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-2 text-left">Code</th>
                    <th class="border px-3 py-2 text-left">Building</th>
                    <th class="border px-3 py-2 text-left">Type</th>
                    <th class="border px-3 py-2 text-left">Capacity</th>
                    <th class="border px-3 py-2 text-left">Status</th>
                    <th class="border px-3 py-2 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rooms as $room)
                    <tr>
                        <td class="border px-3 py-2">{{ $room->code }}</td>
                        <td class="border px-3 py-2">{{ $room->building->name ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ $room->type->name ?? '-' }}</td>
                        <td class="border px-3 py-2">{{ $room->capacity }}</td>
                        <td class="border px-3 py-2">
                            @if ($room->is_active)
                                <span class="text-green-600 font-medium">Active</span>
                            @else
                                <span class="text-red-600 font-medium">Inactive</span>
                            @endif
                        </td>
                       <td class="border px-3 py-2 text-center space-x-2">

                            <a href="{{ route('registrar.room.edit', $room) }}"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-blue-500 text-blue-600 hover:bg-blue-50 text-sm">
                                <!-- Pencil/Edit icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.121 2.121 0 113 3L12 14l-4 1 1-4z" />
                                </svg>
                            </a>

                            {{-- Delete handled by Livewire; simple browser confirm --}}
                            <button
                                x-data
                                @click="if (confirm('Delete this room permanently?')) { $wire.delete({{ $room->id }}) }"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-500 text-red-600 hover:bg-red-50 text-sm">
                                <!-- Trash/Delete icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                </svg>
                            </button>

                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-gray-500">No rooms found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4">
        {{ $rooms->links() }}
    </div>
</div>
