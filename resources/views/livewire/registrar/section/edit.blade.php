{{-- resources/views/livewire/registrar/section/edit.blade.php --}}
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Page Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold">Edit Section</h1>
            <p class="text-sm text-gray-500">Update the details of this section.</p>
        </div>
        <a href="{{ route('registrar.section.index') }}"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border hover:bg-gray-50">
            {{-- simple chevron-left --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Back
        </a>
    </div>

    {{-- Flash success --}}
    @if (session('success'))
        <div class="rounded-lg bg-green-100 text-green-900 px-3 py-2">
            {{ session('success') }}
        </div>
    @endif

    {{-- Validation summary (optional, shows the first error) --}}
    @if ($errors->any())
        <div class="rounded-lg bg-red-50 text-red-700 px-3 py-2">
            <span class="font-medium">Please fix the errors below.</span>
        </div>
    @endif

    {{-- Form Card --}}
    <form wire:submit.prevent="update" class="rounded-xl border p-4 md:p-6 bg-white space-y-5">
        {{-- Course --}}
        <div>
            <label class="block text-sm mb-1">Course <span class="text-red-600">*</span></label>
            <select wire:model="course_id"
                    class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- choose --</option>
                @foreach($courses as $c)
                    <option value="{{ $c->id }}">{{ $c->course_name }}</option>
                @endforeach
            </select>
            @error('course_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Section Name --}}
        <div>
            <label class="block text-sm mb-1">Section Name <span class="text-red-600">*</span></label>
            <input type="text" wire:model.defer="section_name"
                   placeholder="e.g., BSIT 1A"
                   class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            @error('section_name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Year Level --}}
        <div>
            <label class="block text-sm mb-1">Year Level <span class="text-red-600">*</span></label>
            <select wire:model="year_level"
                    class="w-full border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">-- choose --</option>
                @foreach(['First Year','Second Year','Third Year','Fourth Year'] as $yl)
                    <option value="{{ $yl }}">{{ $yl }}</option>
                @endforeach
            </select>
            @error('year_level') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Footer Actions --}}
        <div class="flex items-center gap-2 pt-2">
            <a href="{{ route('registrar.section.index') }}"
               class="px-3 py-2 rounded-lg border hover:bg-gray-50">
               Cancel
            </a>

            <button type="submit"
                    class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove>Update Section</span>
                <span wire:loading>Updating…</span>
            </button>
        </div>
    </form>

    {{-- Small Hint --}}
    <p class="text-xs text-gray-500">
        Tip: Section name can be unique per course. If you see a duplicate error, check other sections under the same course.
    </p>
</div>
