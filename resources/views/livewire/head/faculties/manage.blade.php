<div class="space-y-8">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">Manage Faculty</h1>
      <p class="text-sm text-gray-500">Update user code & employment caps. Department/Course are read-only.</p>
    </div>
    <a href="{{ route('head.faculties.index') }}"
       class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">Back</a>
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
          <div class="text-gray-500">System Department</div>
          <div class="text-gray-900">{{ $user->department?->department_name ?? '—' }}</div>
        </div>
        <div>
          <div class="text-gray-500">Program/Course</div>
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
        {{ method_exists($user, 'maxUnits') ? $user->maxUnits() : (($user->employment?->regular_load ?? 0) + ($user->employment?->extra_load ?? 0)) }}
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
  @if (session('success_availability'))
    <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
      {{ session('success_availability') }}
    </div>
  @endif

  {{-- Responsive columns: Users Dept | Employment | (Availability when PT) --}}
  <div @class([
    'grid grid-cols-1 gap-6',
    'lg:grid-cols-3' => $this->isPartTime,
    'lg:grid-cols-2' => ! $this->isPartTime,
  ])>
    {{-- Users Department (code/position only) --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">Users Department Record</h2>
        <p class="text-sm text-gray-500">Stored in <code>users_deparment</code>. Department is locked.</p>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">User Code ID</label>
          <input type="text" wire:model.defer="user_code_id"
                 class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
          @error('user_code_id') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Position</label>
          <select wire:model.defer="position"
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="Faculty">Faculty</option>
            <option value="Chairperson" disabled>Head</option>
            <option value="Dean" disabled>Dean</option>
          </select>
          @error('position') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Department (locked)</label>
          <input type="text" disabled
                 value="{{ $user->department?->department_name ?? '—' }}"
                 class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
        </div>

        <div class="pt-2">
          <button wire:click="saveDepartment"
                  class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
            Save Department Record
          </button>
        </div>
      </div>
    </div>

    {{-- Employment --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="mb-4">
        <h2 class="text-base font-semibold text-gray-900">Employment</h2>
        <p class="text-sm text-gray-500">Stored in <code>users_employments</code>.</p>
      </div>

      <div class="space-y-3">
        <div>
          <label class="block text-sm text-gray-600 mb-1">Classification</label>
          <select wire:model.defer="employment_classification"
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="Teaching">Teaching</option>
            <option value="Non-Teaching" disabled>Non-Teaching</option>
          </select>
          @error('employment_classification') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div>
          <label class="block text-sm text-gray-600 mb-1">Status</label>
          {{-- LIVE binding so the Availability column appears instantly when Part-Time is chosen --}}
          <select wire:model.live="employment_status"
                  class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            <option value="Full-Time">Full-Time</option>
            <option value="Part-Time">Part-Time</option>
          </select>
          @error('employment_status') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Regular Load (units)</label>
            <input type="number" min="0" max="45" wire:model.defer="regular_load"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            @error('regular_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Extra Load (units)</label>
            <input type="number" min="0" max="24" wire:model.defer="extra_load"
                   class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500">
            @error('extra_load') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
          </div>
        </div>

        @if (!$this->isPartTime)
          <div class="mt-3 text-xs text-gray-500">
            Full-Time faculty are schedulable on weekdays only (Mon–Fri). Your generator should assume 08:00–18:00 availability (no weekends).
          </div>
        @endif

        <div class="pt-2">
          <button wire:click="saveEmployment"
                  class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
            Save Employment
          </button>
        </div>
      </div>
    </div>

    {{-- Availability Column (Part-Time only) --}}
    @if ($this->isPartTime)
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="mb-4">
          <h2 class="text-base font-semibold text-gray-900">Availability (Part-Time)</h2>
          <p class="text-sm text-gray-500">
            Pili ug adlaw (Mon–Fri) ug butang ang oras per day. Inputs are 24-hour time (HH:MM).
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr>
                <th class="text-left border-b p-2">Day</th>
                <th class="text-left border-b p-2">Available?</th>
                <th class="text-left border-b p-2">Start (HH:MM)</th>
                <th class="text-left border-b p-2">End (HH:MM)</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($days as $day)
                <tr class="border-b">
                  <td class="p-2 font-medium">{{ $day }}</td>
                  <td class="p-2">
                    <input type="checkbox"
                           wire:model.live="dayEnabled.{{ $day }}"
                           class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                  </td>
                  <td class="p-2">
                    <input type="time"
                           wire:model.defer="dayStart.{{ $day }}"
                           @disabled(!($dayEnabled[$day] ?? false))
                           class="w-36 rounded border border-gray-300 px-2 py-1 focus:ring-2 focus:ring-emerald-500">
                    @error("dayStart.$day") <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                  </td>
                  <td class="p-2">
                    <input type="time"
                           wire:model.defer="dayEnd.{{ $day }}"
                           @disabled(!($dayEnabled[$day] ?? false))
                           class="w-36 rounded border border-gray-300 px-2 py-1 focus:ring-2 focus:ring-emerald-500">
                    @error("dayEnd.$day") <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="pt-4">
          <button wire:click="saveAvailability"
                  class="rounded-lg bg-emerald-600 px-4 py-2 text-white text-sm hover:bg-emerald-700">
            Save Availability
          </button>
          @if (session('success_availability'))
            <div class="mt-2 rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
              {{ session('success_availability') }}
            </div>
          @endif
        </div>
      </div>
    @endif
  </div>
</div>
