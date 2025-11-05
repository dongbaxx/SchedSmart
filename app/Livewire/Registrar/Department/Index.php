<?php

namespace App\Livewire\Registrar\Department;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\Department;

#[Title('Departments')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';
    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'page'   => ['except' => 1],
    ];

    public function updating($name, $value)
    {
        if ($name === 'search') $this->resetPage();
    }

    public function delete(int $id)
    {
        Department::findOrFail($id)->delete();
        session()->flash('success', 'Department deleted.');
        $this->resetPage();
    }

    public function render()
    {
        $items = Department::query()
            ->when($this->search, fn($q) =>
                $q->where('department_name', 'like', "%{$this->search}%")
                  ->orWhere('department_description', 'like', "%{$this->search}%"))
            ->orderBy('department_name')
            ->paginate($this->perPage);

        return view('livewire.registrar.department.index', compact('items'));
    }
}
