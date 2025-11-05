<?php

namespace App\Livewire\Registrar\Department;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Department;

#[Title('Add Department')]
#[Layout('layouts.registrar-shell')]
class Create extends Component
{
    public string $department_name = '';
    public string $department_description = '';

    protected function rules()
    {
        return [
            'department_name' => ['required','string','max:255','unique:departments,department_name'],
            'department_description' => ['required','string','max:255'],
        ];
    }

    public function save()
    {
        $data = $this->validate();
        Department::create($data);
        session()->flash('success','Department created.');
        return redirect()->route('registrar.department.index');
    }

    public function render()
    {
        return view('livewire.registrar.department.create');
    }
}
