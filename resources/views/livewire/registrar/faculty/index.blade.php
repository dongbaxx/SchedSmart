<div class="space-y-6">

  {{-- page header --}}
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">Users & Roles</h1>
      <div class="text-sm text-gray-500">
        Verify roles, filter by department/course, then manage academic attributes.
      </div>
    </div>

    <a
      href="{{ route('registrar.faculty.create') }}"
      wire:navigate
      class="hidden sm:inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
    >
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
      </svg>
      Add user
    </a>
  </div>

  {{-- filters --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="relative">
      <input
        type="text"
        wire:model.live.debounce.400ms="search"
        placeholder="Search name or email..."
        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
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

    <div>
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

    <div>
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

  {{-- table --}}
  <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-xs uppercase tracking-wider text-gray-500">
        <tr>
          <th class="px-4 py-2">Name</th>
          <th class="px-4 py-2">Email</th>
          <th class="px-4 py-2">Role</th>
          <th class="px-4 py-2">Department</th>
          <th class="px-4 py-2">Course</th>
          <th class="px-4 py-2">Emp. (Reg/Extra)</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($users as $u)
          <tr>
            <td class="px-4 py-2">
              <div class="font-medium text-gray-900">{{ $u->name }}</div>
              <div class="text-xs text-gray-500">Code: {{ $u->userDepartment?->user_code_id ?? '—' }}</div>
            </td>
            <td class="px-4 py-2">{{ $u->email }}</td>
            <td class="px-4 py-2">
              <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-emerald-700 ring-1 ring-inset ring-emerald-200">
                {{ $u->role ?? '—' }}
              </span>
            </td>
            <td class="px-4 py-2">{{ $u->department?->department_name ?? '—' }}</td>
            <td class="px-4 py-2">{{ $u->course?->course_name ?? '—' }}</td>
            <td class="px-4 py-2">
              @php
                $reg = (int)($u->employment?->regular_load ?? 0);
                $ext = (int)($u->employment?->extra_load ?? 0);
              @endphp
              {{ $reg }} / {{ $ext }}
            </td>
          </tr>
        @empty
          <tr>
            <td class="px-4 py-6 text-center text-gray-500" colspan="6">No users found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>
    {{ $users->links() }}
  </div>
</div>
