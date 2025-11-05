<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">Specializations</h1>
      <p class="text-sm text-gray-500">Optional link to a course/program.</p>
    </div>
    <a href="{{ route('head.spec.create') }}" class="rounded-lg bg-emerald-600 text-white px-3 py-2 text-sm">Add Specialization</a>
  </div>

  @if (session('success'))
    <div class="rounded-md bg-emerald-50 text-emerald-800 px-3 py-2 text-sm">{{ session('success') }}</div>
  @endif

  <div class="flex flex-wrap items-center gap-3">
    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Search…" class="w-56 rounded-lg border px-3 py-2 text-sm">
    <select wire:model="departmentId" class="rounded-lg border px-3 py-2 text-sm">
      <option value="">All Departments</option>
      @foreach ($departments as $d)
        <option value="{{ $d->id }}">{{ $d->department_name }}</option>
      @endforeach
    </select>
    <select wire:model="courseId" class="rounded-lg border px-3 py-2 text-sm">
      <option value="">All Courses</option>
      @foreach ($courses as $c)
        <option value="{{ $c->id }}">{{ $c->course_name }}</option>
      @endforeach
    </select>
  </div>

  <div class="overflow-hidden rounded-lg border">
    <table class="min-w-full divide-y">
      <thead class="bg-gray-50 text-xs">
        <tr>
          <th class="px-3 py-2 text-left">Name</th>
          <th class="px-3 py-2 text-left">Course</th>
          <th class="px-3 py-2 text-left">Department</th>
          <th class="px-3 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y bg-white">
        @forelse ($items as $row)
          <tr>
            <td class="px-3 py-2">{{ $row->name }}</td>
            <td class="px-3 py-2">{{ $row->course?->course_name ?? '—' }}</td>
            <td class="px-3 py-2">{{ $row->course?->department?->department_name ?? '—' }}</td>
            <td class="px-3 py-2 text-right">
              <a href="{{ route('head.spec.edit', $row) }}"
               wire:navigate
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border"
                            >
                                <!-- Pencil Square icon -->
                                <svg xmlns="http://www.w3.org/2000/svg"
                                    class="w-5 h-5 text-blue-600"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414a2 2 0 01.586-1.414z" />
                                </svg>
            </a>
              <button
                x-data
                @click="if (confirm('Delete this room permanently?')) { $wire.delete({{ $row->id }}) }"
                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-500 text-red-600 hover:bg-red-50 text-sm">
                <!-- Trash/Delete icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                </svg>
            </button>

            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No results.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $items->links() }}</div>
</div>
