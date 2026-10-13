<?php

namespace App\Livewire;

use App\Models\School;
use App\Models\User;
use App\Support\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Top bar dropdown that sets which school's content the super admin works on.
 */
class SchoolSwitcher extends Component
{
    public ?int $schoolId = null;

    /**
     * The page the switcher was rendered on, reloaded after switching.
     */
    #[Locked]
    public string $returnUrl = '';

    public function mount(CurrentSchool $currentSchool): void
    {
        $this->authorizeSuperAdmin();

        $this->schoolId = $currentSchool->id();
        $this->returnUrl = request()->fullUrl();
    }

    public function updatedSchoolId(mixed $value, CurrentSchool $currentSchool): void
    {
        $this->authorizeSuperAdmin();

        $school = School::find($value);

        if ($school === null) {
            $this->schoolId = $currentSchool->id();

            return;
        }

        $currentSchool->select($school);

        // Reload the page so every school-scoped query runs again.
        $this->redirect($this->returnUrl ?: filament()->getUrl());
    }

    /**
     * @return Collection<int, School>
     */
    public function schools(): Collection
    {
        return School::orderBy('name')->get(['id', 'name', 'is_active']);
    }

    public function render(): View
    {
        return view('livewire.school-switcher', [
            'schools' => $this->schools(),
        ]);
    }

    protected function authorizeSuperAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $user->isSuperAdmin(), 403);
    }
}
