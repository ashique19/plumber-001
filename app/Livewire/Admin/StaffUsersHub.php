<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\StaffUser;
use App\Services\Operations\StaffAdminService;
use App\Support\EnabledLocales;
use App\Support\Staff\StaffAdminRoleCatalog;
use App\Support\Validation\FieldValidationRules;
use App\Business\BusinessContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class StaffUsersHub extends Component
{
    use DispatchesToasts;
    use HasAdminHubPagination;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    #[Url]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = StaffAdminRoleCatalog::UI_STAFF;

    public string $locale = 'bn';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);

        abort_unless(Auth::guard('staff')->user()?->hasRole('owner'), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $user = StaffUser::query()->with('roles')->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->role = StaffAdminRoleCatalog::primaryUiRole($user->getRoleNames());
        $this->locale = EnabledLocales::resolve($user->locale ?? 'bn');
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(StaffAdminService $staffAdmin): void
    {
        /** @var StaffUser $actor */
        $actor = Auth::guard('staff')->user();

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in(StaffAdminRoleCatalog::assignableUiRoles())],
            'locale' => ['required', Rule::in(EnabledLocales::codes())],
        ];
        if ($this->editingId === null) {
            $rules['password'] = ['required', 'string', 'min:12'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:12'];
        }

        $validated = $this->validate($rules);
        $spatieRole = StaffAdminRoleCatalog::spatieRolesForUiRole($validated['role']);

        if ($this->editingId) {
            $user = StaffUser::query()->findOrFail($this->editingId);
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'locale' => $validated['locale'],
            ]);
            if ($validated['password'] !== '') {
                $user->update(['password' => $validated['password']]);
            }
            $staffAdmin->syncRoles($this->business, $user, [$spatieRole], $actor);
            $this->toastSuccess(__('staff_users.updated'));
        } else {
            $staffAdmin->createUser(
                $this->business,
                $validated['name'],
                $validated['email'],
                $validated['password'],
                null,
                [$spatieRole],
                $validated['locale'],
                $actor,
            );
            $this->toastSuccess(__('staff_users.created'));
        }

        $this->closeForm();
    }

    public function deactivateUser(int $id, StaffAdminService $staffAdmin): void
    {
        /** @var StaffUser $actor */
        $actor = Auth::guard('staff')->user();
        $user = StaffUser::query()->findOrFail($id);

        if ($user->is_active) {
            $staffAdmin->setActive($user, false, $actor);
            $this->toastSuccess(__('staff_users.deactivated'));
        }
    }

    public function reactivateUser(int $id, StaffAdminService $staffAdmin): void
    {
        /** @var StaffUser $actor */
        $actor = Auth::guard('staff')->user();
        $user = StaffUser::query()->findOrFail($id);

        if (! $user->is_active) {
            $staffAdmin->setActive($user, true, $actor);
            $this->toastSuccess(__('staff_users.reactivated'));
        }
    }

    public function render(): View
    {
        $query = StaffUser::query()->with('roles')->orderBy('name');
        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        }

        $users = $query->paginate($this->adminPerPage());
        /** @var StaffUser|null $actor */
        $actor = Auth::guard('staff')->user();
        $localeOptions = collect(EnabledLocales::codes())
            ->mapWithKeys(fn (string $code) => [
                $code => (string) config("locales.supported.{$code}.native", strtoupper($code)),
            ])
            ->all();

        return view('livewire.admin.staff-users-hub', [
            'users' => $users,
            'actor' => $actor,
            'localeOptions' => $localeOptions,
            'roleOptions' => StaffAdminRoleCatalog::assignableUiRoles(),
        ])->layout('layouts.pos', ['pageTitle' => __('staff_users.title')]);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->role = StaffAdminRoleCatalog::UI_STAFF;
        $this->locale = 'bn';
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => FieldValidationRules::emailRequired(),
            'password' => ['nullable', 'string', 'min:12'],
        ];
    }
}