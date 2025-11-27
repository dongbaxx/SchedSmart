@php
    $courseName = $user->course?->course_name ?? 'this course';
    $dean = auth()->user();
    $isSelf = $dean && $dean->id === $user->id;
@endphp

<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-emerald-900">
                Academic Specializations
            </h1>
            <div class="text-sm text-gray-600">
                {{ $user->name }}
                <span class="text-gray-400">•</span>
                {{ $user->email }}
                <span class="text-gray-400">•</span>
                Role: {{ $user->role ?? '—' }}
            </div>
            <div class="text-xs text-gray-500">
                Dept: {{ $user->department?->department_name ?? '—' }}
                /
                Course: {{ $user->course?->course_name ?? '—' }}
            </div>
        </div>

        <a href="{{ route('dean.people.index') }}"
           class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
            Back
        </a>
    </div>

    {{-- Flash message --}}
    @if (session('ok'))
        <div class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2 text-sm border border-emerald-200">
            {{ session('ok') }}
        </div>
    @endif

    {{-- Main card --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 space-y-4">

        {{-- Search + Filter Buttons --}}
        <div class="flex flex-wrap items-center gap-3">
            {{-- Search --}}
            <div class="flex-1 min-w-[220px]">
                <input
                    type="text"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search specialization..."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                           focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                >
            </div>

            @if ($isSelf)
                {{-- ✅ Dean editing own profile: fixed to department-wide view --}}
                <span class="text-xs text-gray-500">
                    Showing all specializations under your department
                    ({{ $user->department?->department_name ?? 'this department' }}).
                </span>
            @elseif ($user->course_id)
                {{-- OLD behavior for other users: course-only vs all --}}
                <button
                    type="button"
                    wire:click="$set('filterByCourse', true)"
                    class="text-xs sm:text-sm inline-flex items-center rounded-lg px-3 py-1.5 font-medium
                           @if($filterByCourse)
                               bg-emerald-600 text-white hover:bg-emerald-700
                           @else
                               bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50
                           @endif"
                >
                    View only {{ $courseName }} specializations
                </button>

                <button
                    type="button"
                    wire:click="$set('filterByCourse', false)"
                    class="text-xs sm:text-sm inline-flex items-center rounded-lg px-3 py-1.5 font-medium
                           @if(!$filterByCourse)
                               bg-gray-800 text-white hover:bg-gray-900
                           @else
                               bg-white border border-gray-300 text-gray-700 hover:bg-gray-50
                           @endif"
                >
                    View all specializations
                </button>
            @else
                <span class="text-xs text-gray-400">
                    Course is not set for this faculty, all specializations are shown.
                </span>
            @endif
        </div>

        {{-- State text --}}
        <p class="text-xs text-gray-500">
            @if ($isSelf)
                Showing all specializations from your department
                ({{ $user->department?->department_name ?? '—' }}). General specializations are also included.
            @elseif ($user->course_id)
                @if ($filterByCourse)
                    Showing <span class="font-semibold text-emerald-700">
                        only specializations for {{ $courseName }}
                    </span>. All other courses are hidden.
                @else
                    Showing <span class="font-semibold">all available specializations</span>.
                    Click <span class="font-semibold">“View only {{ $courseName }} specializations”</span>
                    to hide others.
                @endif
            @else
                Showing all available specializations.
            @endif
        </p>

        {{-- List of specializations --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-2">
            @forelse ($specs as $sp)
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 px-3 py-2
                              hover:bg-gray-50 cursor-pointer">
                    <input
                        type="checkbox"
                        wire:model="selected"
                        value="{{ $sp->id }}"
                        class="mt-1 rounded border-gray-300"
                    >
                    <div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $sp->name }}
                        </div>
                        <div class="text-xs text-gray-500 mt-0.5">
                            @if ($sp->course_id)
                                Course: {{ $sp->course?->course_name ?? '—' }}
                            @else
                                General (no specific course)
                            @endif
                        </div>
                    </div>
                </label>
            @empty
                <div class="col-span-full text-sm text-gray-500">
                    No specializations found for the current filters.
                </div>
            @endforelse
        </div>

        {{-- Save button --}}
        <div class="pt-2 flex justify-end">
            <button
                type="button"
                wire:click="save"
                class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm
                       font-medium text-white hover:bg-emerald-700 focus:outline-none
                       focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
            >
                Save changes
            </button>
        </div>
    </div>

    {{-- Currently assigned --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-800 mb-2">
            Currently assigned specializations
        </h2>

        @php
            $current = $user->specializations()
                ->orderBy('name')
                ->pluck('name')
                ->all();
        @endphp

        @if (count($current))
            <div class="flex flex-wrap gap-2">
                @foreach ($current as $name)
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5
                                 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-200">
                        {{ $name }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="text-sm text-gray-500">
                No specializations assigned yet.
            </div>
        @endif
    </div>
</div>
