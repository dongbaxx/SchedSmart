<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-emerald-900">Edit Course</h1>
    <a href="{{ route('registrar.course.index') }}" class="text-sm text-gray-600 hover:underline">Back</a>
  </div>

  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium">Course Name</label>
      <input type="text" wire:model.defer="course_name" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
      @error('course_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium">Description</label>
      <input type="text" wire:model.defer="course_description" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
      @error('course_description') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium">Department</label>
      <select wire:model.defer="department_id" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
        <option value="">— Select Department —</option>
        @foreach ($departments as $d)
          <option value="{{ $d->id }}">{{ $d->department_name }}</option>
        @endforeach
      </select>
      @error('department_id') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>
  </div>

  <button wire:click="save" class="rounded-lg bg-emerald-600 text-white px-3 py-2 text-sm">Update</button>
</div>
