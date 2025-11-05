<?php

namespace App\Livewire\Registrar\Section;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\{Section, Course};
use Illuminate\Validation\Rule;

#[Title('Edit Section')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public Section $section;
    public ?int $course_id = null;
    public ?string $section_name = null;
    public ?string $year_level = null;

    public function mount()
    {
        $this->course_id = $this->section->course_id;
        $this->section_name = $this->section->section_name;
        $this->year_level = $this->section->year_level;
    }

    protected function rules(): array
    {
        return [
            'course_id'    => ['required','exists:courses,id'],
            'section_name' => [
                'required','string','max:255',
                Rule::unique('sections','section_name')
                    ->where(fn($q)=>$q->where('course_id',$this->course_id))
                    ->ignore($this->section->id),
            ],
            'year_level'   => ['required','string'],
        ];
    }

    public function update()
    {
        $this->validate();
        $this->section->update([
            'course_id'=>$this->course_id,
            'section_name'=>$this->section_name,
            'year_level'=>$this->year_level,
        ]);
        session()->flash('success','Section updated.');
        return redirect()->route('registrar.section.index');
    }

    public function render()
    {
        return view('livewire.registrar.section.edit',[
            'courses'=>Course::orderBy('course_name')->get(),
            'yearLevels'=>['First Year','Second Year','Third Year','Fourth Year'],
        ]);
    }
}

