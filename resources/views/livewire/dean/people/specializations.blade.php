{{-- resources/views/livewire/dean/people/specializations.blade.php --}}
<div class="max-w-3xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-emerald-900">
                Faculty Specializations
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

        {{-- Search + Filter --}}
        <div class="flex items-center gap-3">
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Search specialization..."
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
            >

            @if ($user->course_id)
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        wire:model="filterByCourse"
                        class="rounded border-gray-300"
                    >
                    <span>
                        Show only {{ $user->course?->course_name ?? 'this course' }} specializations
                    </span>
                </label>
            @endif
        </div>

        {{-- List of specializations --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @forelse ($specs as $sp)
                <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">
                    <input
                        type="checkbox"
                        wire:model="selected"
                        value="{{ $sp->id }}"
                        class="rounded border-gray-300"
                    >
                    <div>
                        <div class="text-sm font-medium text-gray-900">
                            {{ $sp->name }}
                        </div>
                        <div class="text-xs text-gray-500">
                            @if ($sp->course_id)
                                Course-limited
                            @else
                                (General)
                            @endif
                        </div>
                    </div>
                </label>
            @empty
                <div class="text-sm text-gray-500">
                    No specializations found.
                </div>
            @endforelse
        </div>

        {{-- Save button --}}
        <div class="pt-2 flex justify-end">
            <button
                type="button"
                wire:click="save"
                class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
            >
                Save changes
            </button>
        </div>
    </div>

    {{-- Currently assigned --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4">
        <h2 class="text-sm font-semibold text-gray-800 mb-2">
            Currently assigned
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
                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700 ring-1 ring-inset ring-emerald-200">
                        {{ $name }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="text-sm text-gray-500">
                None yet.
            </div>
        @endif
    </div>
</div>
