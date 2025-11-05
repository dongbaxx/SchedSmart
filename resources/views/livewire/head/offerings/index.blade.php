<div class="space-y-4" wire:poll.10s.keep-alive>
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">My Course Offerings</h1>
        <div class="flex gap-2">
            <a href="{{ route('head.offerings.history') }}"
            class="px-3 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                History
            </a>
            <a href="{{ route('head.offerings.wizards') }}"
            class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                Bulk Generate
            </a>
        </div>
    </div>


    @if(session('offerings_success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded">
            {{ session('offerings_success') }}
        </div>
    @endif
    @if(session('offerings_warning'))
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-2 rounded">
            {{ session('offerings_warning') }}
        </div>
    @endif

    <div class="grid md:grid-cols-3 gap-3">
        <div>
            <label>Academic Term</label>
            <select wire:model.live="academic_id" class="w-full border rounded px-2 py-1">
                <option value="">All</option>
                @foreach($academics as $a)
                    <option value="{{ $a->id }}">{{ $a->school_year }} — {{ $a->semester }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Year Level</label>
            <select wire:model.live="year_level" class="w-full border rounded px-2 py-1">
                <option value="">All</option>
                @foreach($levels as $L)
                    <option value="{{ $L }}">{{ $L }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="border rounded overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2">Term</th>
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
                        {{-- Edit disabled if locked --}}
                        <a href="{{ route('head.offerings.edit',$r) }}"
                           class="px-2 py-1 border rounded text-sm
                                  {{ $r->status==='locked' ? 'pointer-events-none opacity-40' : 'border-blue-500 text-blue-600 hover:bg-blue-50' }}">
                            Edit
                        </a>
                        {{-- Schedule only visible if locked --}}
                        @if($r->status==='locked')
                            <a href="{{ route('head.schedulings.editor',$r) }}"
                               class="px-2 py-1 border border-emerald-500 text-emerald-600 rounded hover:bg-emerald-50 text-sm">
                                Schedule
                            </a>
                        @else
                            <span class="px-2 py-1 border border-gray-300 text-gray-400 rounded text-sm">Schedule</span>
                        @endif
                        {{-- Delete disabled if locked --}}
                        <button wire:click="delete({{ $r->id }})"
                                class="px-2 py-1 border rounded text-sm
                                       {{ $r->status==='locked' ? 'cursor-not-allowed opacity-40 border-gray-300 text-gray-400' : 'border-red-500 text-red-600 hover:bg-red-50' }}">
                            Delete
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">No offerings yet.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $rows->links() }}</div>
</div>
