<div class="flex items-center gap-2">
    <label class="text-xs text-emerald-200">Academic Term</label>

    <select
        class="text-sm bg-emerald-800/60 border border-emerald-700 rounded-md px-2 py-1 focus:outline-none focus:ring-1 focus:ring-emerald-300"
        wire:model.change="selected"
        aria-label="Active Academic Term"
    >
        @foreach ($terms as $t)
            <option value="{{ $t->id }}">
                {{-- Adjust these to match your columns --}}
                {{ $t->school_year ?? (isset($t->year_start, $t->year_end) ? "AY {$t->year_start}-{$t->year_end}" : "AY #{$t->id}") }}
                —
                {{ ucfirst($t->semester ?? ($t->term_name ?? 'Semester')) }}
            </option>
        @endforeach
    </select>

    @if (session()->has('success'))
        <span class="ml-2 text-xs text-emerald-100">{{ session('success') }}</span>
    @endif
    @if (session()->has('error'))
        <span class="ml-2 text-xs text-red-200">{{ session('error') }}</span>
    @endif
</div>
