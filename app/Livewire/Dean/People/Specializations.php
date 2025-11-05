<?php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Specialization, Course};

#[Title('Faculty Specializations')]
#[Layout('layouts.dean-shell')]
class Specializations extends Component
{
    public User $user;
    /** @var array<int> */
    public array $selected = [];
    public string $search = '';
    public bool $filterByCourse = true;

    public function mount(User $user): void
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        // user must be Head/Faculty in same department
        abort_if(
            $user->department_id !== $dean->department_id ||
            !in_array($user->role, [User::ROLE_HEAD, User::ROLE_FACULTY], true),
            403
        );

        $this->user = $user;
        $this->selected = $user->specializations()->pluck('specializations.id')->map(fn($i)=>(int)$i)->toArray();
    }

    public function save(): void
    {
        $ids = $this->selected;

        $query = Specialization::query()->whereIn('id', $ids);

        // when filtering by course: allow general (NULL course_id) or same course
        if ($this->filterByCourse && $this->user->course_id) {
            $query->where(function($q) {
                $q->whereNull('course_id')
                  ->orWhere('course_id', $this->user->course_id);
            });
        }

        $validIds = $query->pluck('id')->all();
        $this->selected = array_values(array_intersect($ids, $validIds));

        $this->user->specializations()->sync($this->selected);

        session()->flash('ok', 'Specializations updated.');
        redirect()->route('dean.people.index');
    }

    public function render()
    {
        $specs = Specialization::query()
            ->when($this->filterByCourse && $this->user->course_id, function($q) {
                $q->where(function($w) {
                    $w->whereNull('course_id')
                      ->orWhere('course_id', $this->user->course_id);
                });
            })
            ->when($this->search !== '', function($q) {
                $s = trim($this->search);
                $q->where('name','like',"%{$s}%");
            })
            ->orderBy('name')
            ->get(['id','name','course_id']);

        return view('livewire.dean.people.specializations', [
            'specs' => $specs,
        ]);
    }
}
