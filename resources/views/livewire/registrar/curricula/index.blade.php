<div class="p-6 space-y-8">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg text-gray-500">List of all added curricula</h3>
        </div>

        <a href="{{ route('registrar.curricula.form') }}"
           wire:navigate
           class="px-4 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700">
            Add Curriculum
        </a>
    </div>

    @if (session('success'))
        <div class="rounded-lg bg-green-50 text-green-700 px-4 py-3 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <!-- FILTERS -->
    <div class="rounded-2xl border bg-white p-4 space-y-3">
        <div class="grid gap-3 md:grid-cols-6">
            <input type="text"
                   wire:model.live.debounce.250ms="search"
                   placeholder="Search code / title / prerequisite"
                   class="w-full border rounded-xl px-3 py-2" />

            <select wire:model.live.debounce.250ms="courseFilter" class="w-full border rounded-xl px-3 py-2">
                <option value="">All Courses</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>

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

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model.live.debounce.250ms="dashUsesFilters" class="rounded border-gray-300">
            <span>Apply filters to Dashboard</span>
        </label>
    </div>

    <!-- DASHBOARD KPI CARDS -->
    <div class="grid gap-4 md:grid-cols-5">
        <div class="rounded-2xl border p-4 bg-white">
            <div class="text-xs text-gray-500">Total Curricula</div>
            <div class="text-2xl font-semibold">{{ $dash['stats']['total'] }}</div>
        </div>
        <div class="rounded-2xl border p-4 bg-white">
            <div class="text-xs text-gray-500">Total Units</div>
            <div class="text-2xl font-semibold">{{ $dash['stats']['total_units'] }}</div>
        </div>
        <div class="rounded-2xl border p-4 bg-white">
            <div class="text-xs text-gray-500">With Prerequisite</div>
            <div class="text-2xl font-semibold">{{ $dash['stats']['with_prereq'] }}</div>
        </div>
        <div class="rounded-2xl border p-4 bg-white">
            <div class="text-xs text-gray-500">With Lab Hours</div>
            <div class="text-2xl font-semibold">{{ $dash['stats']['with_lab'] }}</div>
        </div>
        <div class="rounded-2xl border p-4 bg-white">
            <div class="text-xs text-gray-500">Distinct Courses</div>
            <div class="text-2xl font-semibold">{{ $dash['stats']['distinct_courses'] }}</div>
        </div>
    </div>

    <!-- BREAKDOWNS -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- By Year Level -->
        <div class="rounded-2xl border bg-white">
            <div class="px-4 py-3 border-b font-medium">By Year Level</div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2">Year Level</th>
                            <th class="py-2 text-right">Count</th>
                            <th class="py-2 text-right">Units</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dash['byYear'] as $row)
                            <tr class="border-t">
                                <td class="py-2">{{ $row->year_level }}</td>
                                <td class="py-2 text-right">{{ $row->total }}</td>
                                <td class="py-2 text-right">{{ $row->units }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-500">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- By Semester -->
        <div class="rounded-2xl border bg-white">
            <div class="px-4 py-3 border-b font-medium">By Semester</div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2">Semester</th>
                            <th class="py-2 text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dash['bySem'] as $row)
                            <tr class="border-t">
                                <td class="py-2">{{ $row->semester }}</td>
                                <td class="py-2 text-right">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Courses -->
        <div class="rounded-2xl border bg-white">
            <div class="px-4 py-3 border-b font-medium">Top Courses (by # of curricula)</div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-2">Course</th>
                            <th class="py-2 text-right">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dash['topCourses'] as $row)
                            <tr class="border-t">
                                <td class="py-2">{{ $row->course->course_name ?? '—' }}</td>
                                <td class="py-2 text-right">{{ $row->total }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-4 text-center text-gray-500">No data</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RECENT ADDITIONS -->
    <div class="rounded-2xl border bg-white">
        <div class="px-4 py-3 border-b font-medium">Recent Additions</div>
        <div class="p-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-2 px-2">#</th>
                        <th class="py-2 px-2">Code</th>
                        <th class="py-2 px-2">Title</th>
                        <th class="py-2 px-2">Course</th>
                        <th class="py-2 px-2">Year</th>
                        <th class="py-2 px-2">Sem</th>
                        <th class="py-2 px-2">Units</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dash['recent'] as $r)
                        <tr class="border-t">
                            <td class="py-2 px-2">{{ $r->id }}</td>
                            <td class="py-2 px-2 font-medium">{{ $r->course_code }}</td>
                            <td class="py-2 px-2">{{ $r->descriptive_title }}</td>
                            <td class="py-2 px-2">{{ $r->course->course_name ?? '—' }}</td>
                            <td class="py-2 px-2">{{ $r->year_level }}</td>
                            <td class="py-2 px-2">{{ $r->semester ?? '—' }}</td>
                            <td class="py-2 px-2">{{ $r->units ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-gray-500">No data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- MAIN TABLE (FULL LIST) -->
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
                    <th class="px-4 py-3">Course</th>
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
                        <td class="px-4 py-3">{{ $row->course->course_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $row->year_level }}</td>
                        <td class="px-4 py-3">{{ $row->semester ?? '—' }}</td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <a href="{{ route('registrar.curricula.edit', $row->id) }}"
                               wire:navigate
                               class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15.232 5.232l3.536 3.536M9 13l6.536-6.536a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.414.586H9v-1.414a2 2 0 01.586-1.414z" />
                                </svg>
                            </a>

                            <button x-data
                                @click="if (confirm('Delete this curriculum permanently?')) { $wire.delete({{ $row->id }}) }"
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
                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No results.</td></tr>
                @endforelse
            </tbody>
        </table>

        <!-- Pagination (triggers scroll too) -->
        <div class="p-4" x-data @click.capture="$nextTick(() => Livewire.dispatch('scroll-to-table'))">
            {{ $items->links() }}
        </div>
    </div>

    <!-- FAB (mobile only) -->
    <a href="{{ route('registrar.curricula.form') }}"
       wire:navigate
       class="sm:hidden fixed bottom-5 right-5 inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
       aria-label="Add curriculum" title="Add curriculum">
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

    <div
        x-data="{
            search: @entangle('search'),
            course: @entangle('courseFilter'),
            spec:   @entangle('specFilter'),
            year:   @entangle('yearFilter'),
            sem:    @entangle('semFilter'),
            per:    @entangle('perPage'),
            page:   @entangle('page'),
            go() {
                requestAnimationFrame(() => {
                    const el = document.getElementById('main-table');
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        }"
        x-init="
            $watch('search', () => go());
            $watch('course', () => go());
            $watch('spec',   () => go());
            $watch('year',   () => go());
            $watch('sem',    () => go());
            $watch('per',    () => go());
            $watch('page',   () => go());
        "
    ></div>
</div>
