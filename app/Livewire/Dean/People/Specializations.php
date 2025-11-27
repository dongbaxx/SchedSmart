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

    // Default behavior para sa NON-self users: course-only
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

        // default selected specializations
        $this->selected = $user->specializations()
            ->pluck('specializations.id')
            ->map(fn ($i) => (int) $i)
            ->toArray();

        // ✅ SPECIAL CASE: kung iyang kaugalingon ang gi-open
        // ayaw na gamita ang course-only filter sa initial view,
        // kay ang gusto nimo kay department-wide view.
        if ($dean->id === $user->id) {
            $this->filterByCourse = false;
        }
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
        $dean = Auth::user();
        $isSelf = $dean && $dean->id === $this->user->id;

        $specsQuery = Specialization::query()
            ->with('course');

        if ($isSelf) {
            // ✅ DEAN VIEWING OWN SPECIALIZATIONS:
            // → tanang specializations sa IYANG DEPARTMENT (+ general)
            if ($dean->department_id) {
                $specsQuery->where(function ($q) use ($dean) {
                    $q->whereHas('course', function ($cq) use ($dean) {
                        $cq->where('department_id', $dean->department_id);
                    })
                    ->orWhereNull('course_id'); // include general specs
                });
            }
        } else {
            // ✅ OLD BEHAVIOR for other users:
            // kung filterByCourse = true + naay course_id ang user
            // → specializations nga course_id == user->course_id RA ang makita
            if ($this->filterByCourse && $this->user->course_id) {
                $specsQuery->where('course_id', $this->user->course_id);
            }
            // kung filterByCourse = false → walay extra filter (all)
        }

        // search
        $specsQuery->when($searchTerm !== '', function ($q) use ($searchTerm) {
            $q->where('name', 'like', "%{$searchTerm}%");
        });

        $specs = $specsQuery
            ->orderBy('name')
            ->get(['id', 'name', 'course_id']);

        return view('livewire.dean.people.specializations', [
            'specs' => $specs,
        ]);
    }
}
