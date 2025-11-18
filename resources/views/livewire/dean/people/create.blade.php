{{-- resources/views/livewire/dean/people/create.blade.php --}}

<div class="max-w-3xl space-y-6">

  {{-- flash --}}
  @if(session('ok'))
    <div class="rounded-lg border-l-4 border-emerald-500 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">
      {{ session('ok') }}
    </div>
  @endif

  {{-- header --}}
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-xl font-semibold text-emerald-900">
        {{ $userId ? 'Edit Head/Faculty' : 'Add Head/Faculty' }}
      </h1>
      <p class="text-sm text-gray-500">
        Department is locked to yours; you may reassign the Head/Faculty to any course/program under your department.
      </p>
    </div>

    <a
      href="{{ route('dean.people.index') }}"
      wire:navigate
      class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-200"
    >
      Back
    </a>
  </div>

  <form wire:submit.prevent="save" class="space-y-6 bg-white rounded-2xl border border-gray-200 p-6 shadow-sm">

    {{-- name + email --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Full name <span class="text-red-500">*</span>
        </label>
        <input
          type="text"
          wire:model="name"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
        @error('name')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Email <span class="text-red-500">*</span>
        </label>
        <input
          type="email"
          wire:model="email"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
        @error('email')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>
    </div>

    {{-- password --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Password {{ $userId ? '(leave blank to keep)' : '' }}
        </label>
        <input
          type="password"
          wire:model="password"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
        @error('password')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Confirm Password
        </label>
        <input
          type="password"
          wire:model="password_confirmation"
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
      </div>
    </div>

    {{-- role + course --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Role <span class="text-red-500">*</span>
        </label>
        <select
          wire:model="role"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">— Select —</option>
          @foreach($roles as $r)
            <option value="{{ $r }}">{{ $r }}</option>
          @endforeach
        </select>
        @error('role')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Department
        </label>
        <div class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
          {{ $departmentName }}
        </div>
      </div>

      <div>
        <label class="mb-1 block text-xs font-semibold text-gray-700">
          Course / Program <span class="text-red-500">*</span>
        </label>
        <select
          wire:model="course_id"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
        >
          <option value="">— Select —</option>
          @foreach($courses as $c)
            <option value="{{ $c->id }}">{{ $c->course_name }}</option>
          @endforeach
        </select>
        @error('course_id')
          <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
      </div>
    </div>

    <p class="text-xs text-gray-500">
      Department is locked to yours; only courses in your department are shown.
    </p>

    {{-- actions --}}
    <div class="flex items-center justify-end gap-3 pt-2">
      <a
        href="{{ route('dean.people.index') }}"
        wire:navigate
        class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
      >
        Cancel
      </a>

      <button
        type="submit"
        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500"
      >
        {{ $userId ? 'Update' : 'Create' }}
      </button>
    </div>
  </form>
</div>
