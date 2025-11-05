<?php

namespace App\Livewire\Shared;

use Livewire\Component;
use App\Models\AcademicYear;
use Illuminate\Support\Facades\Schema;

class CurrentAcademicDisplay extends Component
{
    public ?string $label = null;

    public function mount(): void
    {
        // Get active term from DB or session
        $active = null;

        if (Schema::hasColumn('academic_years', 'is_active')) {
            $active = AcademicYear::where('is_active', true)->first();
        }

        if (! $active && session()->has('active_academic_id')) {
            $active = AcademicYear::find((int) session('active_academic_id'));
        }

        // Build text label
        if ($active) {
            $this->label = sprintf(
                'AY %s — %s',
                $active->school_year ?? "{$active->year_start}-{$active->year_end}",
                ucfirst($active->semester ?? ($active->term_name ?? 'Semester'))
            );
        } else {
            $this->label = 'No active academic term';
        }
    }

    public function render()
    {
        return view('livewire.shared.current-academic-display', [
            'label' => $this->label,
        ]);
    }
}
