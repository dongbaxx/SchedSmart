<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SchedSmart' }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-screen bg-gray-100 text-gray-800 antialiased">
<div class="h-full flex">

    {{-- SIDEBAR (Registrar only) --}}
    <aside class="w-64 bg-emerald-900 text-white flex flex-col">
        <div class="h-16 flex items-center gap-3 px-5 border-b border-emerald-800">
            {{-- LOGO + NAME (no circle/border) --}}
            <img
                src="{{ asset('images/sfxc_logo.png') }}"
                alt="SFXC Logo"
                class="h-10 w-10 object-contain"
            >
            <span class="text-xl font-bold">
                SchedSmart
            </span>
        </div>

        @php
            /** @var \App\Models\User|null $u */
            $u = auth()->user();

            $items = [
                [
                    'label'   => 'Dashboard',
                    'route'   => 'registrar.dashboard',
                    'pattern' => 'registrar.dashboard',
                    // Grid icon
                    'icon'    => 'M3 3h7v7H3V3zM14 3h7v7h-7V3zM3 14h7v7H3v-7zM14 14h7v7h-7v-7z',
                ],
                [
                    'label'   => 'Academic Terms',
                    'route'   => 'registrar.academic.index',
                    'pattern' => 'registrar.academic.*',
                    'icon'    => 'M4 4h16v2H4zM4 10h16v2H4zM4 16h10v2H4z', // menu icon
                ],
                [
                    'label'   => 'Faculty & Users',
                    'route'   => 'registrar.faculty.index',
                    'pattern' => 'registrar.faculty*',
                    // Users icon
                    'icon'    => 'M17 20h5v-2a4 4 0 00-4-4h-1m-8 6H4v-2a4 4 0 014-4h1m8-6a3 3 0 11-6 0 3 3 0 016 0M10 8a3 3 0 11-6 0 3 3 0 016 0',
                ],
                [
                    'label'   => 'Department',
                    'route'   => 'registrar.department.index',
                    'pattern' => 'registrar.department*',
                    // Building icon
                    'icon'    => 'M3 21v-9a1 1 0 011-1h16a1 1 0 011 1v9M9 21v-6h6v6M9 3v3h6V3M4 6h16v3H4V6z',
                ],
                [
                    'label'   => 'Course',
                    'route'   => 'registrar.course.index',
                    'pattern' => 'registrar.course*',
                    // Book icon
                    'icon'    => 'M4 19.5A2.5 2.5 0 016.5 17H20m0 0V6a2 2 0 00-2-2H6.5A2.5 2.5 0 014 6.5v13z',
                ],
                [
                    'label'   => 'Specialization',
                    'route'   => 'registrar.specialization.index',
                    'pattern' => 'registrar.specialization*',
                    // Academic cap icon
                    'icon'    => 'M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 01-9-9m9 9a9 9 0 009-9',
                ],
                [
                    'label'   => 'Rooms',
                    'route'   => 'registrar.room.index',
                    'pattern' => 'registrar.room*',
                    // Home/door icon
                    'icon'    => 'M3 9.75L12 3l9 6.75V21a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1V9.75z',
                ],
                [
                    'label'   => 'Section',
                    'route'   => 'registrar.section.index',
                    'pattern' => 'registrar.section*',
                    // Rectangle stack icon
                    'icon'    => 'M3 7h18M3 12h18M3 17h18',
                ],
                [
                    'label'   => 'Curricula',
                    'route'   => 'registrar.curricula.index',
                    'pattern' => 'registrar.curricula*',
                    // Document text icon
                    'icon'    => 'M7 2a2 2 0 00-2 2v16a2 2 0 002 2h10a2 2 0 002-2V8l-6-6H7zM13 3.5V9h5.5',
                ],
                [
                    'label'   => 'Offerings',
                    'route'   => 'registrar.offering.index',
                    'pattern' => 'registrar.offering*',
                    // Clipboard list icon
                    'icon'    => 'M9 2a1 1 0 00-1 1v1H5a2 2 0 00-2 2v13a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-3V3a1 1 0 00-1-1H9zm0 0h6v4H9V2zm1 7h4m-4 4h4m-4 4h4',
                ],
            ];
        @endphp

        <nav class="flex-1 px-3 py-4 space-y-2">
            @foreach($items as $it)
                @php $active = request()->routeIs($it['pattern']); @endphp
                <a href="{{ route($it['route']) }}"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg transition
                          {{ $active ? 'bg-emerald-700/90' : 'hover:bg-emerald-800/80' }}">
                    <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $it['icon'] }}"/>
                    </svg>
                    <span class="text-sm font-medium">{{ $it['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="p-4 border-t border-emerald-800 space-y-3">
            {{-- profile quick row (CLICKABLE -> settings.profile) --}}
            @php
                $initials = method_exists($u, 'initials')
                  ? $u?->initials()
                  : strtoupper(mb_substr($u?->name ?? $u?->email ?? 'U', 0, 1));
                $onSettings = request()->routeIs('settings.profile');
            @endphp

            <a href="{{ route('settings.profile') }}"
               class="flex items-center gap-3 px-2 py-2 rounded-lg transition group
                      {{ $onSettings ? 'bg-emerald-800/80 ring-2 ring-emerald-500/60' : 'hover:bg-emerald-800/70' }}"
               title="Open Profile Settings">
                <div class="w-9 h-9 rounded-full bg-emerald-700 flex items-center justify-center text-sm font-semibold">
                    {{ $initials }}
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold truncate group-hover:underline">{{ $u?->name }}</div>
                    <div class="text-xs text-emerald-200 truncate">{{ $u?->role }}</div>
                </div>
            </a>

            {{-- logout --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-emerald-800 hover:bg-emerald-700 text-sm">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- MAIN --}}
    <div class="flex-1 flex flex-col">

        <header class="h-14 bg-emerald-900 text-white border-b border-emerald-800 flex items-center justify-between px-6 shadow-sm">
            <h1 class="text-lg font-semibold truncate">{{ $title ?? 'Dashboard' }}</h1>

            <div>
                @can('is-registrar')
                    @livewire('registrar.academic.term-switcher')
                @endcan
            </div>
        </header>

        <main class="flex-1 overflow-y-auto">
            <div class="px-6 py-5">
                <div class="bg-gray-50 rounded-xl border border-gray-200 p-6 shadow-sm">
                    {{ $slot }}
                </div>
            </div>
        </main>

        <footer class="h-12 text-xs text-gray-500 flex items-center justify-center border-t bg-white">
            © {{ date('Y') }} SchedSmart. All rights reserved.
        </footer>
    </div>
</div>

@livewireScripts
</body>
</html>
