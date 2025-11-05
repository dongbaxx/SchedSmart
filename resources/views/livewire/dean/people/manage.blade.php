{{-- resources/views/livewire/dean/people/manage.blade.php --}}
<div class="space-y-8">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">Manage Academic Attributes</h1>
      <p class="text-sm text-gray-500">Within your department only.</p>
    </div>
    <a href="{{ route('dean.people.index') }}" class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">Back</a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="col-span-2 rounded-xl border border-gray-200 bg-white p-4">
      <div class="flex items-start justify-between">
        <div>
          <div class="text-lg font-medium text-gray-900">{{ $user->name }}</div>
          <div class="text-sm text-gray-500">{{ $user->email }}</div>
        </div>
        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-inset ring-emerald-200 text-xs">
          {{ $roleBadge }}
        </span>
      </div>
      <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
        <div>
          <div class="text-gray-500">Department</div>
          <div class="text-gray-900">{{ $user->department?->department_name ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Course</div>
          <div class="text-gray-900">{{ $user->course?->course_name ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">User Code</div>
          <div class="text-gray-900">{{ $user->userDepartment?->user_code_id ?? '—' }}</div>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4">
      <div class="text-sm text-gray-500">Max Units (computed)</div>
      <div class="text-2xl font-semibold text-emerald-700 mt-1">
        {{ method_exists($user,'maxUnits') ? $user->maxUnits() : (($user->employment->regular_load ?? 0)+($user->employment->extra_load ?? 0)) }}
      </div>
      <div class="text-xs text-gray-500 mt-2">Regular + Extra from Employment</div>
    </div>
  </div>

  @if (session('success_department'))
    <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
      {{ session('success_department') }}
    </div>
  @endif
  @if (session('success_employment'))
    <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
      {{ session('success_employment') }}
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">Users Department Record</h2>
        <p class="text-sm text-gray-500">Stored in <code>users_deparment</code>. (Locked to your department)</p>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">User Code ID</label>
          <input type="text" wire:model.defer="user_code_id" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
          @error('user_code_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Position</label>
          <select wire:model.defer="position" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="">— Select —</option>
            <option value="Faculty">Faculty</option>
            <option value="Head">Head</option>
          </select>
          @error('position') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Department</label>
          <select wire:model.defer="dept_department_id" disabled class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm">
            @foreach($departments as $d)
              <option value="{{ $d->id }}">{{ $d->department_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="pt-2">
          <button wire:click="saveDepartment" class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
            Save Department Record
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">Employment</h2>
        <p class="text-sm text-gray-500">Stored in <code>users_employments</code>.</p>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Classification</label>
          <select wire:model.defer="employment_classification" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="Teaching">Teaching</option>
            <option value="Non-Teaching">Non-Teaching</option>
          </select>
          @error('employment_classification') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Status</label>
          <select wire:model.defer="employment_status" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="Full-Time">Full-Time</option>
            <option value="Part-Time">Part-Time</option>
            <option value="Contractual">Contractual</option>
          </select>
          @error('employment_status') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Regular Load (units)</label>
            <input type="number" min="0" max="45" wire:model.defer="regular_load" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            @error('regular_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Extra Load (units)</label>
            <input type="number" min="0" max="24" wire:model.defer="extra_load" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            @error('extra_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
          </div>
        </div>

        <div class="pt-2">
          <button wire:click="saveEmployment" class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
            Save Employment
          </button>
        </div>
      </div>
    </div>

  </div>
</div>
