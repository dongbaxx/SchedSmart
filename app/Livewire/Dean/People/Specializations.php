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

        // Default OFF — para makita tanan sa first load,
        // unya kung i-check ang checkbox, BSIT-only (or course-only) ra
        public bool $filterByCourse = false;

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

            // Base query sa gipang-check nga IDs
            $query = Specialization::query()->whereIn('id', $ids);

            // ALWAYS limit sa same course + general (NULL),
            // para dili maka-assign ug specializations sa lain nga course.
            if ($this->user->course_id) {
                $query->where(function ($q) {
                    $q->whereNull('course_id')
                    ->orWhere('course_id', $this->user->course_id);
                });
            }

            $validIds = $query->pluck('id')->all();

            // i-filter ang selected IDs base sa validIds
            $this->selected = array_values(array_intersect($ids, $validIds));

            $this->user->specializations()->sync($this->selected);

            session()->flash('ok', 'Specializations updated.');
            redirect()->route('dean.people.index');
        }

        public function render()
        {
            $specs = Specialization::query()
                // VIEW FILTER:
                // kung naka-check ang "Show only for this course",
                // ipakita lang ang specializations nga exact same course_id.
                ->when($this->filterByCourse && $this->user->course_id, function ($q) {
                    $q->where('course_id', $this->user->course_id);
                })
                // kung wala gi-check, makita tanan (general + all courses)
                ->when($this->search !== '', function ($q) {
                    $s = trim($this->search);
                    $q->where('name', 'like', "%{$s}%");
                })
                ->orderBy('name')
                ->get(['id', 'name', 'course_id']);

            return view('livewire.dean.people.specializations', [
                'specs' => $specs,
            ]);
        }
    }
