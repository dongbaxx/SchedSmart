<?php

namespace App\Livewire\Head\Faculties;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Specialization};

#[Title('Specializations')]
#[Layout('layouts.head-shell')]
class Specializations extends Component
{
    public User $user;

    /** @var array<int> */
    public array $selected = [];   // specialization IDs

    public string $search = '';

    // ✅ Default: true → pag-open pa lang sa page, course-only na ang makita
    public bool $filterByCourse = true;

    public function mount(User $user): void
    {
        // Head only
        abort_unless(Auth::check() && Auth::user()->role === User::ROLE_HEAD, 403);

        $this->user = $user;

        $this->selected = $user->specializations()
            ->pluck('specializations.id')
            ->map(fn ($i) => (int) $i)
            ->toArray();
    }

    public function save(): void
    {
        $ids = $this->selected;

        // Safety: ang ma-save ra kay general + same course sa faculty
        $query = Specialization::query()->whereIn('id', $ids);

        if ($this->user->course_id) {
            $query->where(function ($q) {
                $q->whereNull('course_id')
                  ->orWhere('course_id', $this->user->course_id);
            });
        }

        $validIds = $query->pluck('id')->all();

        // Drop invalid IDs kung naay injection
        $this->selected = array_values(array_intersect($ids, $validIds));

        $this->user->specializations()->sync($this->selected);

        session()->flash('ok', 'Specializations updated.');
        redirect()->route('head.faculties.index');
    }

    public function render()
    {
        $searchTerm = trim($this->search);

        $specs = Specialization::query()
            ->with('course')

            // ✅ VIEW FILTER:
            // kung filterByCourse = true + naay course_id ang user
            // → ANG MAKITA RA: specializations nga course_id == user->course_id
            ->when($this->filterByCourse && $this->user->course_id, function ($q) {
                $q->where('course_id', $this->user->course_id);
            })

            // search by name
            ->when($searchTerm !== '', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            })

            ->orderBy('name')
            ->get(['id', 'name', 'course_id']);

        return view('livewire.head.faculties.specializations', [
            'specs' => $specs,
        ]);
    }
}
