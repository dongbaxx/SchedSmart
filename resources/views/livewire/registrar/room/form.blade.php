<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold">Add Room</h1>
            <p class="text-sm text-gray-500">Create a new room</p>
        </div>

        <a href="{{ route('registrar.room.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border hover:bg-gray-50">← Back</a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-700 px-3 py-2">{{ session('success') }}</div>
    @endif

    <form wire:submit.prevent="save" class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm mb-1">Code <span class="text-red-600">*</span></label>
            <input type="text" wire:model.defer="code" class="w-full rounded-lg border px-3 py-2">
            @error('code')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Building</label>
            <select wire:model.defer="building_id" class="w-full rounded-lg border px-3 py-2">
                <option value="">—</option>
                @foreach($buildings as $b)
                    <option value="{{ $b->id }}">{{ $b->name }}</option>
                @endforeach
            </select>
            @error('building_id')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Type</label>
            <select wire:model.defer="room_type_id" class="w-full rounded-lg border px-3 py-2">
                <option value="">—</option>
                @foreach($types as $t)
                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
            </select>
            @error('room_type_id')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
        </div>

        <div>
            <label class="block text-sm mb-1">Capacity</label>
            <input type="number" wire:model.defer="capacity" class="w-full rounded-lg border px-3 py-2">
            @error('capacity')<div class="text-sm text-red-600 mt-1">{{ $message }}</div>@enderror
        </div>

        <label class="inline-flex items-center gap-2 md:col-span-2">
            <input type="checkbox" wire:model.defer="is_active" class="rounded">
            <span class="text-sm">Active</span>
        </label>

        <div class="md:col-span-2 flex gap-3">
            <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
                Save
            </button>
            <a href="{{ route('registrar.room.index') }}" class="px-4 py-2 rounded-xl border">Cancel</a>
        </div>
    </form>
</div>
