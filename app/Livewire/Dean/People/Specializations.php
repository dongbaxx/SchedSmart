<?php

namespace App\Livewire\Dean\People;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\{User, Specialization};

#[Title('Academic Specializations')]
#[Layout('layouts.dean-shell')]
class Specializations extends Component
{
    public User $user;

    /** @var array<int> */
    public array $selected = [];

    public string $search = '';

    // ✅ Default: TRUE para pag-sulod sa page, course-only na daan ang makita
    public bool $filterByCourse = true;

    public function mount(User $user): void
    {
        $dean = Auth::user();
        abort_unless($dean && $dean->role === User::ROLE_DEAN, 403);

        // user must be Dean/Head/Faculty in same department
        abort_if(
            $user->department_id !== $dean->department_id ||
            !in_array($user->role, [User::ROLE_DEAN, User::ROLE_HEAD, User::ROLE_FACULTY], true),
            403
        );

        $this->user = $user;

        $this->selected = $user->specializations()
            ->pluck('specializations.id')
            ->map(fn ($i) => (int) $i)
            ->toArray();
    }

    public function save(): void
    {
        $ids = $this->selected;

        // Safety: ang ma-save ra kay general + same course sa user
        $query = Specialization::query()->whereIn('id', $ids);

        if ($this->user->course_id) {
            $query->where(function ($q) {
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
        $searchTerm = trim($this->search);

        $specs = Specialization::query()
            ->with('course')

            // ✅ Default behavior: pag-sulod sa page, filterByCourse = true
            //     → specializations nga course_id == user->course_id RA ang makita
            ->when($this->filterByCourse && $this->user->course_id, function ($q) {
                $q->where('course_id', $this->user->course_id);
            })

            // search
            ->when($searchTerm !== '', function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%");
            })

            ->orderBy('name')
            ->get(['id', 'name', 'course_id']);

        return view('livewire.dean.people.specializations', [
            'specs' => $specs,
        ]);
    }
}
