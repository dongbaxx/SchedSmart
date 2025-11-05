<?php

namespace App\Livewire\Registrar\Department;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Department;

#[Title('Edit Department')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public Department $department;

    public string $department_name = '';
    public string $department_description = '';

    protected function rules()
    {
        return [
            'department_name' => ['required','string','max:255','unique:departments,department_name,' . $this->department->id],
            'department_description' => ['required','string','max:255'],
        ];
    }

    public function mount(Department $department)
    {
        $this->department = $department;
        $this->department_name = $department->department_name;
        $this->department_description = $department->department_description;
    }

    public function save()
    {
        $data = $this->validate();
        $this->department->update($data);
        session()->flash('success','Department updated.');
        return redirect()->route('registrar.department.index');
    }

    public function render()
    {
        return view('livewire.registrar.department.edit');
    }
}
