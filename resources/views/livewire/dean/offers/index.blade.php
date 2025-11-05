<div class="space-y-4" wire:poll.7s.keep-alive>
    {{-- Header with History button --}}
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Course Offerings</h1>

        <a href="{{ route('dean.offers.history') }}"
           class="inline-flex items-center rounded-lg bg-indigo-600 text-white px-3 py-2 text-sm shadow hover:bg-indigo-700">
            History
        </a>
    </div>

    {{-- Flash messages --}}
    @if (session('offerings_success'))
        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-2 text-sm">
            {{ session('offerings_success') }}
        </div>
    @endif
    @if (session('offerings_warning'))
        <div class="rounded-md bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-2 text-sm">
            {{ session('offerings_warning') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="grid md:grid-cols-4 gap-3">
        <div>
            <label class="text-sm text-gray-600">Academic Term</label>
            <select wire:model.live="academic_id" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($academics as $a)
                    <option value="{{ $a->id }}">{{ $a->school_year }} — {{ $a->semester }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-gray-600">Program/Course</label>
            <select wire:model.live="course_id" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->course_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm text-gray-600">Year Level</label>
            <select wire:model.live="year_level" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($levels as $L)
                    <option value="{{ $L }}">{{ $L }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden border rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2">Term</th>
                <th class="px-3 py-2">Program</th>
                <th class="px-3 py-2">Year Level</th>
                <th class="px-3 py-2">Section</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2 text-center">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="border-t">
                    <td class="px-3 py-2">{{ $r->academic?->school_year }} — {{ $r->academic?->semester }}</td>
                    <td class="px-3 py-2">{{ $r->course?->course_name }}</td>
                    <td class="px-3 py-2">{{ $r->year_level }}</td>
                    <td class="px-3 py-2">{{ $r->section?->section_name }}</td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-0.5 rounded text-xs
                            @if($r->status==='locked') bg-emerald-100 text-emerald-800
                            @elseif($r->status==='pending') bg-amber-100 text-amber-800
                            @else bg-gray-200 text-gray-700 @endif">
                            {{ ucfirst($r->status ?? 'draft') }}
                        </span>
                    </td>
                    <td class="px-3 py-2 text-center space-x-2">
                        @if(($r->status ?? 'draft')!=='locked')
                            <button wire:click="approve({{ $r->id }})"
                                    class="px-2 py-1 border border-emerald-500 text-emerald-600 rounded hover:bg-emerald-50">
                                Approve
                            </button>
                        @endif
                        <button wire:click="delete({{ $r->id }})"
                                class="px-2 py-1 border border-red-500 text-red-600 rounded hover:bg-red-50">
                            Delete
                        </button>
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

    {{-- Pagination --}}
    <div>{{ $rows->links() }}</div>
</div>
