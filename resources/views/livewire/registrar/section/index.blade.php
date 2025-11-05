{{-- resources/views/livewire/registrar/section/index.blade.php --}}
<div class="space-y-4">
    @if (session('success'))
        <div class="rounded-lg bg-green-100 text-green-900 px-3 py-2">{{ session('success') }}</div>
    @endif

    <div class="flex items-center gap-2">
        <input type="text" wire:model.live="q" placeholder="Search section…" class="border rounded-lg px-3 py-1.5 w-56">
        <select wire:model.live="course_id" class="border rounded-lg px-3 py-1.5">
            <option value="">All Courses</option>
            @foreach($courses as $c)
                <option value="{{ $c->id }}">{{ $c->course_name }}</option>
            @endforeach
        </select>
        <select wire:model.live="year_level" class="border rounded-lg px-3 py-1.5">
            <option value="">All Years</option>
            @foreach(['First Year','Second Year','Third Year','Fourth Year'] as $yl)
                <option value="{{ $yl }}">{{ $yl }}</option>
            @endforeach
        </select>

        <a href="{{ route('registrar.section.create') }}"
           class="ml-auto inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700">
           Add Section
        </a>
    </div>

    <div class="overflow-x-auto border rounded-lg">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="text-left p-2">Section</th>
                <th class="text-left p-2">Year Level</th>
                <th class="text-left p-2">Course</th>
                <th class="text-right p-2">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr class="border-t">
                    <td class="p-2">{{ $row->section_name }}</td>
                    <td class="p-2">{{ $row->year_level }}</td>
                    <td class="p-2">{{ $row->course?->course_name }}</td>
                    <td class="p-2 text-right">
                        <a href="{{ route('registrar.section.edit', $row) }}"

                    class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-blue-500 text-blue-600 hover:bg-blue-50 text-sm">
                                <!-- Pencil/Edit icon -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.232 5.232l3.536 3.536M9 11l6.232-6.232a2.121 2.121 0 113 3L12 14l-4 1 1-4z" />
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
                <tr><td colspan="4" class="p-4 text-center text-gray-500">No results.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $rows->links() }}
</div>
