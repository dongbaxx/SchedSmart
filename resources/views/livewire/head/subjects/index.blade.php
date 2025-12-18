<div class="p-6 space-y-8">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg text-gray-500">List of your subjects (Head)</h3>
            <p class="text-sm text-gray-400">Only subjects under your assigned course are shown.</p>
        </div>

        <a href="{{ route('head.subjects.form') }}"
           wire:navigate
           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
            Add Subject
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-700 px-4 py-3">
            {{ session('success') }}
        </div>
    @endif

    <!-- FILTERS -->
    <div class="rounded-2xl border bg-white p-4 space-y-3">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text"
                   wire:model.live.debounce.250ms="search"
                   placeholder="Search code / title / prerequisite"
                   class="w-full border rounded-xl px-3 py-2" />

            <select wire:model.live.debounce.250ms="yearFilter" class="w-full border rounded-xl px-3 py-2">
                <option value="">All Year Levels</option>
                @foreach($yearLevels as $yl)
                    <option value="{{ $yl }}">{{ $yl }}</option>
                @endforeach
            </select>

            <select wire:model.live.debounce.250ms="semFilter" class="w-full border rounded-xl px-3 py-2">
                <option value="">All Semesters</option>
                @foreach($semesters as $sm)
                    <option value="{{ $sm }}">{{ $sm }}</option>
                @endforeach
            </select>

            <select wire:model.live.debounce.250ms="specFilter" class="w-full border rounded-xl px-3 py-2">
                <option value="">All Specializations</option>
                @foreach($specializations as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </select>

            <select wire:model.live.debounce.250ms="perPage" class="w-full border rounded-xl px-3 py-2">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
                <option value="100">100 / page</option>
            </select>
        </div>
    </div>

    <!-- MAIN TABLE -->
    <div id="main-table"
         class="overflow-x-auto bg-white rounded-2xl border scroll-mt-24"
         wire:loading.class="opacity-50">

        <table class="min-w-full">
            <thead>
                <tr class="text-left border-b">
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Units</th>
                    <th class="px-4 py-3">LEC/LAB</th>
                    <th class="px-4 py-3">Year</th>
                    <th class="px-4 py-3">Semester</th>
                    <th class="px-8 py-3 text-right">Action</th>
                </tr>
            </thead>

            <tbody>
                @forelse($items as $row)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $row->course_code }}</td>
                        <td class="px-4 py-3">{{ $row->descriptive_title }}</td>
                        <td class="px-4 py-3">{{ $row->units ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ $row->lec ?? 0 }}Lc/{{ $row->lab ?? 0 }}Lb
                        </td>
                        <td class="px-4 py-3">{{ $row->year_level }}</td>
                        <td class="px-4 py-3">{{ $row->semester ?? '—' }}</td>

                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('head.subjects.edit', $row->id) }}"
                               wire:navigate
                               class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414a2 2 0 01.586-1.414z" />
                                </svg>
                            </a>

                            <button x-data
                                @click="if (confirm('Delete this subject permanently?')) { $wire.delete({{ $row->id }}) }"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-500 text-red-600 hover:bg-red-50 text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4m-4 0a1 1 0 00-1 1v1h6V4a1 1 0 00-1-1m-4 0h4" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No results.</td></tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination (triggers scroll too) -->
        <div class="p-4" x-data @click.capture="$nextTick(() => Livewire.dispatch('scroll-to-table'))">
            {{ $items->links() }}
        </div>
    </div>

    <!-- FAB (mobile only) -->
    <a href="{{ route('head.subjects.form') }}"
       wire:navigate
       class="sm:hidden fixed bottom-5 right-5 inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
       aria-label="Add subject" title="Add subject">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16M4 12h16"/>
        </svg>
    </a>

    <!-- ✅ Auto-scroll logic -->
    <script>
    document.addEventListener('livewire:init', () => {
        Livewire.on('scroll-to-table', () => {
            const el = document.getElementById('main-table');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    </script>
</div>
