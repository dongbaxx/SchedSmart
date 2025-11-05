<?php

namespace App\Livewire\Registrar\Room;

use App\Models\Room;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Add Room')]
#[Layout('layouts.registrar-shell')]
class Form extends Component
{
    public string $code = '';
    public ?int $building_id = null;
    public ?int $room_type_id = null;
    public ?int $capacity = null;
    public bool $is_active = true;

    public function save()
    {
        $data = $this->validate([
            'code'         => ['required','string','max:50','unique:rooms,code'],
            'building_id'  => ['nullable','exists:buildings,id'],
            'room_type_id' => ['nullable','exists:room_types,id'],
            'capacity'     => ['nullable','integer','min:0'],
            'is_active'    => ['boolean'],
        ]);

        Room::create($data);

        session()->flash('success', 'Room created.');
        return redirect()->route('registrar.room.index');
    }

    public function render()
    {
        // load dropdown data if needed
        $buildings = \App\Models\Building::query()->select('id','name')->orderBy('name')->get();
        $types     = \App\Models\RoomType::query()->select('id','name')->orderBy('name')->get();

        return view('livewire.registrar.room.form', compact('buildings','types'));
    }
}
