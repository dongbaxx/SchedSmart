{{-- resources/views/livewire/registrar/offering/history.blade.php --}}
<div class="space-y-5">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Course Offerings — History</h1>
            <p class="text-sm text-gray-500">
                View offerings from past terms (and archived).
            </p>
        </div>
        <a href="{{ route('registrar.offering.index') }}"
           class="inline-flex items-center rounded-lg border px-3 py-2 text-sm hover:bg-gray-50">
            ← Back to Offerings
        </a>
    </div>

    <div class="grid md:grid-cols-5 gap-3">
        {{-- Term --}}
        <div>
            <label class="text-sm text-gray-600">Academic Term</label>
            <select wire:model.live="academic_id" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All past terms</option>
                @foreach($academics as $a)
                    <option value="{{ $a->id }}">{{ $a->school_year }} — {{ $a->semester }}</option>
                @endforeach
            </select>
        </div>

        {{-- Course --}}
        <div>
            <label class="text-sm text-gray-600">Program/Course</label>
            <select wire:model.live="course_id" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->course_name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Year Level --}}
        <div>
            <label class="text-sm text-gray-600">Year Level</label>
            <select wire:model.live="year_level" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="">All</option>
                @foreach($levels as $L)
                    <option value="{{ $L }}">{{ $L }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div>
            <label class="text-sm text-gray-600">Status</label>
            <select wire:model.live="status" class="w-full border rounded-xl px-3 py-2 text-sm">
                <option value="archived">Archived</option>
                <option value="locked">Locked</option>
                <option value="pending">Pending</option>
                <option value="draft">Draft</option>
                <option value="all">All</option>
            </select>
        </div>

        {{-- Search --}}
        <div>
            <label class="text-sm text-gray-600">Search</label>
            <input type="text" wire:model.live="search"
                   placeholder="Course / Section / Level"
                   class="w-full border rounded-xl px-3 py-2 text-sm">
        </div>
    </div>

    <div class="flex items-center gap-4">
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live="only_non_active_terms"
                   class="rounded border-gray-300">
            <span>Hide active term</span>
        </label>

        @if(!empty($counts))
            <div class="text-xs text-gray-500">
                @foreach($counts as $k => $v)
                    <span class="mr-2">
                        <span class="px-1.5 py-0.5 rounded bg-gray-100">{{ $k }}</span> {{ $v }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="overflow-hidden border rounded-xl">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left">Term</th>
                <th class="px-3 py-2 text-left">Program</th>
                <th class="px-3 py-2 text-left">Year Level</th>
                <th class="px-3 py-2 text-left">Section</th>
                <th class="px-3 py-2 text-left">Status</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr class="border-t">
                    <td class="px-3 py-2">
                        {{ $r->academicYear?->school_year ?? '—' }} — {{ $r->academicYear?->semester ?? '—' }}
                    </td>
                    <td class="px-3 py-2">{{ $r->course?->course_name ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $r->year_level ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $r->section?->section_name ?? '—' }}</td>
                    <td class="px-3 py-2">
                        <span class="px-2 py-1 text-xs rounded bg-gray-100">{{ $r->status ?? '—' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-3 py-6 text-center text-gray-500">
                        No history records match your filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>
        {{ $rows->links() }}
    </div>
</div>
