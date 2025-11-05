<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Offerings History</h1>
        <a href="{{ route('head.offerings.index') }}"
           class="px-3 py-2 bg-gray-600 text-white rounded">Back to Current</a>
    </div>

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
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $r)
                    <tr class="border-t">
                        <td class="px-3 py-2">{{ $r->academicYear?->school_year }} — {{ $r->academicYear?->semester }}</td>
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-500">No previous offerings found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
