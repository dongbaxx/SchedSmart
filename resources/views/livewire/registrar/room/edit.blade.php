<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Edit Room</h1>
            <p class="text-sm text-gray-500">Update room details</p>
        </div>

        <a href="{{ route('registrar.room.index') }}"
           wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border hover:bg-gray-50">
            ← Back
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-700 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    <!-- SAME FORM LAYOUT AS ADD -->
    <form wire:submit.prevent="update" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Building -->
            <div>
                <label class="block text-sm font-medium mb-1">Building <span class="text-red-500">*</span></label>
                <select wire:model="building_id" class="w-full rounded-lg border px-3 py-2">
                    <option value="" disabled>Select building</option>
                    @foreach ($buildings as $b)
                        <option value="{{ $b->id }}">{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('building_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Room Type -->
            <div>
                <label class="block text-sm font-medium mb-1">Room Type <span class="text-red-500">*</span></label>
                <select wire:model="room_type_id" class="w-full rounded-lg border px-3 py-2">
                    <option value="" disabled>Select type</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                    @endforeach
                </select>
                @error('room_type_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Code -->
            <div>
                <label class="block text-sm font-medium mb-1">Room Code <span class="text-red-500">*</span></label>
                <input type="text" wire:model.defer="code" placeholder="e.g., IT-401"
                       class="w-full rounded-lg border px-3 py-2">
                @error('code') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Capacity -->
            <div>
                <label class="block text-sm font-medium mb-1">Capacity <span class="text-red-500">*</span></label>
                <input type="number" wire:model.defer="capacity" min="1"
                       class="w-full rounded-lg border px-3 py-2">
                @error('capacity') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Active -->
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" wire:model="is_active" class="rounded border-gray-300">
                    <span class="text-sm">Active</span>
                </label>
                @error('is_active') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('registrar.room.index') }}"
               wire:navigate
               class="px-4 py-2 rounded-xl border hover:bg-gray-50">Cancel</a>

            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                Save Changes
            </button>
        </div>
    </form>
</div>
