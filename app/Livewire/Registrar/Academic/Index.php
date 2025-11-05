<?php

namespace App\Livewire\Registrar\Academic;

use Livewire\Component;
use App\Models\AcademicYear;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Title('Academic Terms')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    public $search = '';

    public function delete($id)
    {
        $academic = AcademicYear::find($id);

        if ($academic) {
            $academic->delete();
            session()->flash('success', 'Academic term deleted successfully.');
        } else {
            session()->flash('error', 'Academic term not found.');
        }
    }

    public function render()
    {
        $academic_years = AcademicYear::query()
            ->when($this->search, function ($query) {
                $query->where('school_year', 'like', '%' . $this->search . '%')
                      ->orWhere('semester', 'like', '%' . $this->search . '%');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('livewire.registrar.academic.index', [
            'academic_years' => $academic_years,
        ]);
    }
}
