<?php

namespace App\Livewire\Registrar\Section;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use App\Models\{Section, Course};

#[Title('Sections')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    public string $q = '';
    public ?int $course_id = null;
    public ?string $year_level = null;

    public function updating($field){ if(in_array($field,['q','course_id','year_level'])) $this->resetPage(); }

    public function delete(int $id)
    {
        Section::findOrFail($id)->delete();
        session()->flash('success', 'Section deleted.');
        $this->resetPage();
    }

    public function render()
    {
        $courses = Course::orderBy('course_name')->get();

        $rows = Section::with('course')
            ->when($this->q, fn($q)=>$q->where('section_name','like',"%{$this->q}%"))
            ->when($this->course_id, fn($q)=>$q->where('course_id',$this->course_id))
            ->when($this->year_level, fn($q)=>$q->where('year_level',$this->year_level))
            ->orderBy('course_id')->orderBy('year_level')->orderBy('section_name')
            ->paginate(10);

        return view('livewire.registrar.section.index', compact('rows','courses'));
    }
}

