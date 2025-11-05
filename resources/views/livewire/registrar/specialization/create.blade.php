<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-emerald-900">Add Specialization</h1>
    <a href="{{ route('registrar.specialization.index') }}" class="text-sm text-gray-600 hover:underline">Back</a>
  </div>

  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium">Name</label>
      <input type="text" wire:model.defer="name" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
      @error('name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium">Course (optional)</label>
      <select wire:model.defer="course_id" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        <option value="">— None —</option>
        @foreach ($courses as $c)
          <option value="{{ $c->id }}">{{ $c->course_name }} ({{ $c->department->department_name }})</option>
        @endforeach
      </select>
      @error('course_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>
  </div>

  <button wire:click="save" class="rounded-lg bg-emerald-600 text-white px-3 py-2 text-sm">Create</button>
</div>
    