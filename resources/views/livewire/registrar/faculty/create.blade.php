<div class="max-w-2xl mx-auto space-y-6">

  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-emerald-900">
      {{ $userId ? 'Edit Faculty/User' : 'Add Faculty/User' }}
    </h1>
    <a href="{{ route('registrar.faculty.index') }}"
       class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">Back</a>
  </div>

  @if (session('ok'))
    <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
      {{ session('ok') }}
    </div>
  @endif

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
          Password {{ $userId ? '(leave blank to keep)' : '' }}
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

    {{-- ROLE (use live binding to trigger updated() instantly) --}}
    <div>
      <label class="block text-sm text-gray-600 mb-1">Role</label>
      <select wire:model.live="role"
              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
        <option value="">— Select —</option>
        @foreach($roles as $r)
          <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
      </select>
      @error('role') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
    </div>

    {{-- DEPARTMENT (Dean/Head/Faculty only) --}}
    @if($showDepartment)
      <div>
        <label class="block text-sm text-gray-600 mb-1">Department</label>
        <select wire:model.live="department_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
          <option value="">— Select —</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
          @endforeach
        </select>
        @error('department_id') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>
    @endif

    {{-- COURSE (Head/Faculty only; filtered by department) --}}
    @if($showCourse)
      <div>
        <label class="block text-sm text-gray-600 mb-1">Course / Program</label>
        <select wire:model.live="course_id"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500"
                @disabled(!$department_id)>
          <option value="">— Select —</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
          @endforeach
        </select>
        @error('course_id') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
      </div>
    @endif

    <div class="pt-2">
      <button type="submit"
              class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
        {{ $userId ? 'Update' : 'Create' }}
      </button>
    </div>

  </form>
</div>
