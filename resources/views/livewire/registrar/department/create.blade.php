<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-emerald-900">Add Department</h1>
    <a href="{{ route('registrar.department.index') }}" class="text-sm text-gray-600 hover:underline">Back</a>
  </div>

  <div class="space-y-4">
    <div>
      <label class="block text-sm font-medium">Name</label>
      <input type="text" wire:model.defer="department_name" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
      @error('department_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium">Description</label>
      <input type="text" wire:model.defer="department_description" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
      @error('department_description') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>
  </div>

  <button wire:click="save" class="rounded-lg bg-emerald-600 text-white px-3 py-2 text-sm">Create</button>
</div>
