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
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($users as $u)
                    @php
                        $reg = (int)($u->employment?->regular_load ?? 0);
                        $ext = (int)($u->employment?->extra_load ?? 0);
                        $isCurrentUser = auth()->id() === $u->id;
                    @endphp

                    <tr @class([
                        'bg-emerald-50/60' => $isCurrentUser,
                    ])>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-2">
                                <div>
                                    <div class="font-medium text-slate-900">{{ $u->name }}</div>
                                    <div class="text-xs text-slate-500">
                                        Code: {{ $u->userDepartment?->user_code_id ?? '—' }}
                                    </div>
                                </div>

                                @if($isCurrentUser)
                                    <span class="inline-flex items-center rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white">
                                        (Head)
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2 text-slate-700">{{ $u->email }}</td>
                        <td class="px-4 py-2">{{ $u->department?->department_name ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $u->course?->course_name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            {{ $reg }} / {{ $ext }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <div class="inline-flex items-center gap-1">
                                {{-- Edit (Livewire) --}}
                                <button
                                    type="button"
                                    wire:click="edit({{ $u->id }})"
                                    class="inline-flex items-center gap-1 rounded-lg bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50"
                                >
                                    Edit
                                </button>

                                <a href="{{ route('head.faculties.manage', $u->id) }}"
                                wire:navigate.relaod
                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-white text-xs hover:bg-emerald-700">
                                Manage
                                </a>


                                {{-- Specializations --}}
                                <a href="{{ route('head.faculties.specializations', $u) }}"
                                   class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-white text-xs hover:bg-indigo-700">
                                   Specializations
                                </a>
                            </div>
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
