<div class="p-6 space-y-4">

    {{-- Banner --}}
    <div class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-slate-700">
        @if($course)
            Showing <b>Faculty</b> for <b>{{ $course->course_name }}</b> only.
        @else
            <b>No course assigned</b> to your account. Ask the Registrar to set your course.
        @endif
    </div>

    {{-- Controls: Add + Search --}}
    <div class="flex items-center justify-between gap-3">


        <input type="text" wire:model.live.debounce.400ms="search"
               placeholder="Search faculty by name or email..."
               class="w-full max-w-xs rounded-xl border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">

        <a
          href="{{ route('head.faculties.create') }}"
          wire:navigate
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
          </svg>
          Add user
        </a>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Email</th>
                    <th class="px-4 py-2">Department</th>
                    <th class="px-4 py-2">Course</th>
                    <th class="px-4 py-2">Emp. (Reg/Extra)</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $u)
                    <tr>
                        <td class="px-4 py-2">
                            <div class="font-medium text-slate-900">{{ $u->name }}</div>
                            <div class="text-xs text-slate-500">Code: {{ $u->userDepartment?->user_code_id ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ $u->email }}</td>
                        <td class="px-4 py-2">{{ $u->department?->department_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $u->course?->course_name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            @php $reg=(int)($u->employment?->regular_load ?? 0); $ext=(int)($u->employment?->extra_load ?? 0); @endphp
                            {{ $reg }} / {{ $ext }}
                        </td>
                        <td class="px-4 py-2 text-right space-x-1">
                            <a href="{{ route('head.faculties.manage', $u) }}"
                               class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-white text-xs hover:bg-emerald-700">
                               Manage
                            </a>
                            <a href="{{ route('head.faculties.specializations', $u) }}"
                               class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-white text-xs hover:bg-indigo-700">
                               Specializations
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">
                            {{ $course ? 'No matching faculty found.' : 'Nothing to display.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>
</div>
