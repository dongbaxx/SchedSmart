<?php

namespace App\Livewire\Registrar\Room;

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Models\{Room as RoomModel, Building, RoomType};
use Illuminate\Validation\Rule;

#[Title('Edit Room')]
#[Layout('layouts.registrar-shell')]
class Edit extends Component
{
    public RoomModel $room;

    // same fields as Add
    public $building_id = '';
    public $room_type_id = '';
    public $code = '';
    public $capacity = 1;
    public $is_active = true;

    public $buildings = [];
    public $types = [];

    public function mount(RoomModel $room): void
    {
        $this->room = $room;

        // dropdown data
        $this->buildings = Building::orderBy('name')->get(['id', 'name']);
        $this->types     = RoomType::orderBy('name')->get(['id', 'name']);

        // prefill from existing room
        $this->building_id  = (string) $room->building_id;
        $this->room_type_id = (string) $room->room_type_id;
        $this->code         = $room->code;
        $this->capacity     = (int) $room->capacity;
        $this->is_active    = (bool) $room->is_active;
    }

    protected function rules(): array
    {
        return [
            'building_id'  => ['required', 'integer', Rule::exists('buildings', 'id')],
            'room_type_id' => ['required', 'integer', Rule::exists('room_types', 'id')],
            'code'         => ['required', 'string', 'max:50', Rule::unique('rooms', 'code')->ignore($this->room->id)],
            'capacity'     => ['required', 'integer', 'min:1', 'max:10000'],
            'is_active'    => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'building_id.required'  => 'Please select a building.',
            'room_type_id.required' => 'Please select a room type.',
            'code.required'         => 'Room code is required.',
            'code.unique'           => 'This room code is already taken.',
            'capacity.required'     => 'Capacity is required.',
        ];
    }

    public function update(): void
    {
        $this->code = strtoupper(trim($this->code));
        $data = $this->validate();

        $this->room->update([
            'building_id'  => (int) $data['building_id'],
            'room_type_id' => (int) $data['room_type_id'],
            'code'         => $data['code'],
            'capacity'     => (int) $data['capacity'],
            'is_active'    => (bool) $data['is_active'],
        ]);

        session()->flash('success', 'Room updated successfully.');
        redirect()->route('registrar.room.index');
    }

    public function render()
    {
        return view('livewire.registrar.room.edit');
    }
}
