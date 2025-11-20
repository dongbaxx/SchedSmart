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

  {{-- SIDEBAR --}}
  <aside class="w-64 bg-emerald-900 text-white flex flex-col">
    <div class="h-16 flex items-center gap-3 px-5 border-b border-emerald-800">
      {{-- LOGO + SYSTEM NAME (no circle) --}}
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
          'route'   => 'head.dashboard',
          'pattern' => 'head.dashboard',
          // Squares 2x2 (grid)
          'icon'    => 'M3 3h7v7H3V3zM14 3h7v7h-7V3zM3 14h7v7H3v-7zM14 14h7v7h-7v-7z',
        ],
        [
          'label'   => 'Faculties',
          'route'   => 'head.faculties.index',
          'pattern' => 'head.faculties*',
          // Users group
          'icon'    => 'M17 20h5v-2a4 4 0 00-4-4h-1M9 20H4v-2a4 4 0 014-4h1m8-6a3 3 0 11-6 0 3 3 0 016 0M10 8a3 3 0 11-6 0 3 3 0 016 0',
        ],
        [
          'label'   => 'Specializations',
          'route'   => 'head.spec.index',
          'pattern' => 'head.spec*',
          // Academic cap
          'icon'    => 'M12 14l9-5-9-5-9 5 9 5zm0 0v6m0 0a9 9 0 01-9-9m9 9a9 9 0 009-9',
        ],
        [
          'label'   => 'Offerings',
          'route'   => 'head.offerings.index',
          'pattern' => 'head.offerings*',
          // Clipboard / document
          'icon'    => 'M9 2a1 1 0 00-1 1v1H5a2 2 0 00-2 2v13a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-3V3a1 1 0 00-1-1H9zm0 0h6v4H9V2z',
        ],
        [
          'label'   => 'Loads',
          'route'   => 'head.loads',
          'pattern' => 'head.loads',
          // Clipboard with lines icon
         'icon'    => 'M4 4h16v4H4z M6 10h12v2H6z M8 14h8v2H8z',
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

    {{-- HEADER (with current academic term) --}}
    <header class="h-14 bg-emerald-900 text-white border-b border-emerald-800 flex items-center justify-between px-6 shadow-sm">
      <h1 class="text-lg font-semibold truncate">{{ $title ?? 'Dashboard' }}</h1>

      <div>
        {{-- Display only current academic term (readonly) --}}
        @livewire('shared.current-academic-display')
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
