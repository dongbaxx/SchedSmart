<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\{User, Specialization};

#[Title(' Specializations')]
#[Layout('layouts.head-shell')]
class Specializations extends Component
{
    public User $user;
    /** @var array<int> */
    public array $selected = [];   // specialization IDs
    public string $search = '';
    public bool $filterByCourse = true; // toggle: show specs only for user's course

    public function mount(User $user): void
    {
        // Registrar only (adjust if Dean/Head should manage within scope)
        abort_unless(Auth::check() && Auth::user()->role === \App\Models\User::ROLE_HEAD, 403);

        $this->user = $user;
        $this->selected = $user->specializations()->pluck('specializations.id')->map(fn($i) => (int)$i)->toArray();
    }

    public function save(): void
    {
        // validate that all selected exist (and optionally match course)
        $ids = $this->selected;

        $query = Specialization::query()->whereIn('id', $ids);
        if ($this->filterByCourse && $this->user->course_id) {
            $query->where(function($q) {
                $q->whereNull('course_id')
                  ->orWhere('course_id', $this->user->course_id);
            });
        }
        $validIds = $query->pluck('id')->all();

        // if someone tries to inject invalid ids, drop them
        $this->selected = array_values(array_intersect($ids, $validIds));

        $this->user->specializations()->sync($this->selected);

        session()->flash('ok', 'Specializations updated.');
        redirect()->route('head.faculties.index');
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

        return view('livewire.head.faculties.specializations', [
            'specs' => $specs,
        ]);
    }
}
