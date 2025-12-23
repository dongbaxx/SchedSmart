<?php

namespace App\Livewire\Registrar\Room;

use App\Models\Room;
use Livewire\WithPagination;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;

#[Title('Room')]
#[Layout('layouts.registrar-shell')]
class Index extends Component
{
    use WithPagination;

    // Optional: use Tailwind-styled pagination
    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete($id): void
    {
        $room = Room::findOrFail($id);

        // instead of deleting, just deactivate
        $room->update(['is_active' => 0]);

        session()->flash('success', 'Room set to Inactive successfully.');
        $this->resetPage();
    }



    public function render()
    {
        $rooms = Room::query()
            ->with(['building:id,name', 'type:id,name'])
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('code', 'like', "%{$this->search}%")
                        ->orWhereHas('building', fn($b) => $b->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('type', fn($t) => $t->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->latest('id')
            ->paginate(10);

        return view('livewire.registrar.room.index', [
            'rooms' => $rooms,
        ]);
    }
}
