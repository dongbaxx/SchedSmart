<div class="max-w-2xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-emerald-900">
      {{ $isEdit ? 'Edit Faculty' : 'Add Faculty' }}
    </h1>
    <a href="{{ route('head.faculties.index') }}"
       class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">Back</a>
  </div>

  @if (session('ok'))
    <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
      {{ session('ok') }}
    </div>
  @endif

  <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
    <div class="mb-2 text-gray-600">
      @if($isEdit)
        Editing user under:
      @else
        New user will be created as <b>Faculty</b> under:
      @endif
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div>
        <div class="text-gray-500">Department</div>
        <div class="font-medium text-gray-900">{{ $dept->department_name ?? '—' }}</div>
      </div>
      <div>
        <div class="text-gray-500">Course</div>
        <div class="font-medium text-gray-900">{{ $course->course_name ?? '—' }}</div>
      </div>
    </div>
  </div>

  <form wire:submit.prevent="save" class="space-y-4 bg-white p-6 rounded-xl border border-gray-200">
    <div>
      <label class="block text-sm text-gray-600 mb-1">Full name</label>
      <input type="text" wire:model.defer="name"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
      @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div>
      <label class="block text-sm text-gray-600 mb-1">Email</label>
      <input type="email" wire:model.defer="email"
             class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
      @error('email') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">
          Password
          @if($isEdit)
            <span class="text-xs text-gray-400">(leave blank to keep current)</span>
          @endif
        </label>
        <input type="password" wire:model.defer="password"
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
        @error('password') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Confirm Password</label>
        <input type="password" wire:model.defer="password_confirmation"
               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
      </div>
    </div>

    <button type="submit"
            class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
      {{ $isEdit ? 'Save changes' : 'Create Faculty' }}
    </button>
  </form>
</div>
