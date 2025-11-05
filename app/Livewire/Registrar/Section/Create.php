<?php

namespace App\Livewire\Registrar\Section;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use App\Models\{Section, Course};
use Illuminate\Validation\Rule;

#[Title('Add Section')]
#[Layout('layouts.registrar-shell')]
class Create extends Component
{
    public ?int $course_id = null;
    public ?string $section_name = null;
    public ?string $year_level = null;

    protected function rules(): array
    {
        return [
            'course_id'    => ['required','exists:courses,id'],
            'section_name' => [
                'required','string','max:255',
                // optional uniqueness within course:
                Rule::unique('sections','section_name')->where(fn($q)=>$q->where('course_id',$this->course_id)),
            ],
            'year_level'   => ['required','string'],
        ];
    }

    public function save()
    {
        $this->validate();
        Section::create([
            'course_id'=>$this->course_id,
            'section_name'=>$this->section_name,
            'year_level'=>$this->year_level,
        ]);
        session()->flash('success','Section created.');
        return redirect()->route('registrar.section.index');
    }

    public function render()
    {
        return view('livewire.registrar.section.create',[
            'courses'=>Course::orderBy('course_name')->get(),
            'yearLevels'=>['First Year','Second Year','Third Year','Fourth Year'],
        ]);
    }
}
