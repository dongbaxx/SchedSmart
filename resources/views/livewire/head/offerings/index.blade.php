<div class="space-y-4" wire:poll.10s.keep-alive>

    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">My Course Offerings</h1>

        <div class="flex gap-2">
            <a href="{{ route('head.offerings.wizards') }}"
               class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                Bulk Generate
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-2 rounded">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-3">
        <div>
            <label class="text-sm font-medium">Academic Term</label>
            <select wire:model.live="academic_id" class="w-full border rounded px-2 py-2">
                <option value="">All</option>
                @foreach($academics as $a)
                    <option value="{{ $a->id }}">{{ $a->school_year }} — {{ $a->semester }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="border rounded overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left">Term</th>
                <th class="px-3 py-2 text-left">Year Level</th>
                <th class="px-3 py-2 text-left">Section</th>
                <th class="px-3 py-2 text-center">Pending</th>
                <th class="px-3 py-2 text-center">Locked</th>
                <th class="px-3 py-2 text-center">Actions</th>
            </tr>
            </thead>

            <tbody>
            @forelse($rows as $r)
                <tr class="border-t">
                    <td class="px-3 py-2">{{ $r->term_label }}</td>

                    <td class="px-3 py-2 font-semibold">{{ $r->year_level_label }}</td>
                    <td class="px-3 py-2 font-semibold">{{ $r->section_label }}</td>

                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">
                            {{ $r->pending_count }}
                        </span>
                    </td>

                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded text-xs bg-emerald-100 text-emerald-800">
                            {{ $r->locked_count }}
                        </span>
                    </td>

                    <td class="px-3 py-2 text-center border-blue-300">
                        @if($r->can_schedule)
                            <a href="{{ route('head.schedulings.editor', ['academic' => $r->academic_id]) }}"
                            class="inline-flex px-2 py-1 rounded text-sm text-white bg-emerald-600 hover:bg-emerald-700">
                                Schedule
                            </a>
                        @else
                            <button type="button"
                                class="inline-flex px-2 py-1 rounded text-sm text-white bg-emerald-600 hover:bg-emerald-700">
                                Schedule
                            </button>
                        @endif
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">No offerings yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
