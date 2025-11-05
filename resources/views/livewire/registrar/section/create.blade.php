{{-- resources/views/livewire/registrar/section/form.blade.php --}}
<div class="max-w-xl space-y-4">
    <div>
        <label class="block text-sm mb-1">Course</label>
        <select wire:model="course_id" class="border rounded-lg px-3 py-1.5 w-full">
            <option value="">-- choose --</option>
            @foreach($courses as $c)
                <option value="{{ $c->id }}">{{ $c->course_name }}</option>
            @endforeach
        </select>
        @error('course_id') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1">Section Name</label>
        <input type="text" wire:model="section_name" class="border rounded-lg px-3 py-1.5 w-full" placeholder="e.g., BSIT 1A">
        @error('section_name') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm mb-1">Year Level</label>
        <select wire:model="year_level" class="border rounded-lg px-3 py-1.5 w-full">
            <option value="">-- choose --</option>
            @foreach($yearLevels as $yl)
                <option value="{{ $yl }}">{{ $yl }}</option>
            @endforeach
        </select>
        @error('year_level') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-2">
        <a href="{{ route('registrar.section.index') }}" class="px-3 py-1.5 rounded-lg border">Cancel</a>
        <button wire:click="save" class="px-3 py-1.5 rounded-lg bg-blue-600 text-white">Save</button>
    </div>
</div>
