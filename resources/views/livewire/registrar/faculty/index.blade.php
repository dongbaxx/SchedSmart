<div class="space-y-6">

  {{-- page header --}}
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">Faculty & Users</h1>
      <p class="text-sm text-gray-500">
        Verify roles, filter by department and course, then manage user accounts and academic attributes.
      </p>
    </div>

    {{-- Add user button (desktop) --}}
    <button
      type="button"
      wire:click="create"
      class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
    >
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
      </svg>
      Add user
    </button>

    {{-- Add user (mobile icon button) --}}
    <button
      type="button"
      wire:click="create"
      class="sm:hidden inline-flex items-center justify-center rounded-full bg-emerald-600 p-2 text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
      aria-label="Add user"
    >
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
      </svg>
    </button>
  </div>

  {{-- filters --}}
  <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
    <div class="relative md:col-span-2">
      <input
        type="text"
        wire:model.live.debounce.400ms="search"
        placeholder="Search by name or email..."
        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm pr-9 focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
      <svg class="pointer-events-none absolute right-3 top-2.5 h-5 w-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/>
      </svg>
    </div>

    <div>
      <select
        wire:model.live="role"
        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        <option value="">All roles</option>
        @foreach($roles as $r)
          <option value="{{ $r }}">{{ $r }}</option>
        @endforeach
      </select>
    </div>

    <div class="flex gap-3 md:col-span-1">
      <div class="w-1/2">
        <select
          wire:model.live="departmentId"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">All departments</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->department_name }}</option>
          @endforeach
        </select>
      </div>

      <div class="w-1/2">
        <select
          wire:model.live="courseId"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">All courses</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}">{{ $c->course_name }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  {{-- table --}}
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
        <tr>
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2 hidden sm:table-cell">Email</th>
          <th class="px-4 py-2">Role</th>
          <th class="px-4 py-2 hidden md:table-cell">Department</th>
          <th class="px-4 py-2 hidden lg:table-cell">Course</th>
          <th class="px-4 py-2 text-center">Emp. (Reg/Extra)</th>
          <th class="px-4 py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($users as $u)
          <tr class="align-top">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900">{{ $u->name }}</div>
              <div class="text-xs text-gray-500">
                Code: {{ $u->userDepartment?->user_code_id ?? '—' }}
              </div>
              <div class="mt-0.5 text-xs text-gray-400 sm:hidden">
                {{ $u->email }}
              </div>
            </td>

            <td class="px-4 py-3 hidden sm:table-cell text-gray-700">
              {{ $u->email }}
            </td>

            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                {{ $u->role ?? '—' }}
              </span>
            </td>

            <td class="px-4 py-3 hidden md:table-cell text-gray-700">
              {{ $u->department?->department_name ?? '—' }}
            </td>

            <td class="px-4 py-3 hidden lg:table-cell text-gray-700">
              {{ $u->course?->course_name ?? '—' }}
            </td>

            <td class="px-4 py-3 text-center text-gray-700">
              @php
                $reg = (int)($u->employment?->regular_load ?? 0);
                $ext = (int)($u->employment?->extra_load ?? 0);
              @endphp
              <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-0.5 text-xs text-gray-700 ring-1 ring-inset ring-gray-200">
                {{ $reg }} / {{ $ext }}
              </span>
            </td>

            <td class="px-4 py-3 text-right">
              <div class="inline-flex items-center gap-1">
                <button
                  type="button"
                  wire:click="edit({{ $u->id }})"
                  class="rounded-md bg-white px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50"
                >
                  Edit
                </button>

                <button
                  type="button"
                  onclick="if (confirm('Delete this user?')) { @this.delete({{ $u->id }}) }"
                  class="rounded-md bg-white px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-200 hover:bg-red-50"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td class="px-4 py-6 text-center text-gray-500" colspan="7">
              No users found for the current filters.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="flex items-center justify-between">
    <p class="text-xs text-gray-500">
      Showing
      <span class="font-semibold text-gray-700">{{ $users->firstItem() ?? 0 }}</span>
      –
      <span class="font-semibold text-gray-700">{{ $users->lastItem() ?? 0 }}</span>
      of
      <span class="font-semibold text-gray-700">{{ $users->total() }}</span>
      users
    </p>
    <div>
      {{ $users->links() }}
    </div>
  </div>
</div>
