<?php

namespace App\Livewire\Registrar\Academic;

use Livewire\Component;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TermSwitcher extends Component
{
    public ?int $selected = null;

    /** @var EloquentCollection<AcademicYear> */
    public EloquentCollection $terms;

    public function mount(): void
    {
        // Load dropdown choices (newest first)
        $this->terms = AcademicYear::orderByDesc('id')->get();

        // Determine current active term from column or session
        $active = Schema::hasColumn('academic_years', 'is_active')
            ? AcademicYear::where('is_active', true)->first()
            : AcademicYear::find((int) session('active_academic_id'));

        $this->selected = $active?->id ?? $this->terms->first()?->id;

        if ($this->selected) {
            session(['active_academic_id' => (int) $this->selected]);
        }
    }

    public function updatedSelected($value): void
    {
        $value = (int) $value;

        // Validate choice
        if (! AcademicYear::whereKey($value)->exists()) {
            session()->flash('error', 'Invalid term selected.');
            return;
        }

        $currentId = (int) session('active_academic_id');
        if ($currentId === $value) {
            session()->flash('success', 'Active term is already set.');
            // Still go back to dashboard to keep behavior consistent
            $this->redirectRoute('registrar.dashboard', ['academic' => $value], navigate: true);
            return;
        }

        try {
            DB::transaction(function () use ($value) {
                // If you have an is_active flag, keep it in sync
                if (Schema::hasColumn('academic_years', 'is_active')) {
                    AcademicYear::where('id', '!=', $value)->update(['is_active' => 0]);
                    AcademicYear::where('id', $value)->update(['is_active' => 1]);
                }

                // Persist selected term to session
                session(['active_academic_id' => $value]);
            });

            $this->selected = $value;
            $this->terms = AcademicYear::orderByDesc('id')->get();

            // Notify other Livewire components if you want to listen to this
            $this->dispatch('term-changed', activeAcademicId: $value, previousAcademicId: $currentId);

            session()->flash('success', 'Active term updated.');

            // >>> Redirect back to Registrar Dashboard with ?academic=<id>
            $this->redirectRoute('registrar.dashboard', ['academic' => $value]);


        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', 'Failed to update the active term.');
        }
    }

    public function render()
    {
        return view('livewire.registrar.academic.term-switcher', [
            'terms' => $this->terms,
        ]);
    }
}
