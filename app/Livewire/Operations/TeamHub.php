<?php

namespace App\Livewire\Operations;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Support\Geo\GeoMapsUrl;
use App\Support\Validation\FieldValidationRules;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Operations\BranchAdminService;
use App\Services\Operations\ShiftService;
use App\Services\Operations\StaffAdminService;
use App\Business\BusinessContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class TeamHub extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use ValidatesFieldsLive;


    #[Url]
    public string $tab = 'staff';

    #[Url(as: 'branch')]
    public ?int $shiftBranchId = null;

    public bool $showStaffForm = false;

    public ?int $editingStaffId = null;

    public string $staffName = '';

    public string $staffEmail = '';

    public string $staffPassword = '';

    public ?int $staffBranchId = null;

    /** @var list<string> */
    public array $staffRoles = ['cashier'];

    public bool $staffActive = true;

    public bool $showBranchForm = false;

    public ?int $editingBranchId = null;

    public string $branchCode = '';

    public string $branchName = '';

    public string $branchNameAr = '';

    public string $branchAddress = '';

    public string $branchPhone = '';

    public string $branchEmail = '';

    public string $branchCity = '';

    public bool $branchActive = true;

    public string $branchLat = '';

    public string $branchLng = '';

    public string $branchMapsUrl = '';

    public bool $branchLocationAdvanced = false;

    public string $openingFloat = '';

    public string $closingCounted = '';

    public string $closeNotes = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);

        if ($this->shiftBranchId === null) {
            $this->shiftBranchId = Branch::query()
                ->withoutGlobalScopes()
                ->orderBy('name')
                ->value('id');
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['staff', 'branches', 'shifts'], true)) {
            $this->tab = $tab;
        }
    }

    public function startStaff(?int $id = null): void
    {
        $this->resetStaffForm();
        $this->showStaffForm = true;
        if ($id !== null) {
            $user = $this->staffQuery()->with('roles')->findOrFail($id);
            $this->editingStaffId = $user->id;
            $this->staffName = $user->name;
            $this->staffEmail = $user->email;
            $this->staffBranchId = $user->branch_id;
            $this->staffActive = (bool) $user->is_active;
            $this->staffRoles = $user->getRoleNames()->all();
        }
    }

    public function saveStaff(StaffAdminService $staffAdmin): void
    {
        $rules = [
            'staffName' => ['required', 'string', 'max:120'],
            'staffEmail' => ['required', 'email', 'max:255', Rule::unique('staff_users', 'email')->ignore($this->editingStaffId)],
            'staffBranchId' => ['nullable', 'integer'],
            'staffRoles' => ['required', 'array', 'min:1'],
            'staffActive' => ['boolean'],
        ];
        if ($this->editingStaffId === null) {
            $rules['staffPassword'] = ['required', 'string', 'min:12'];
        } else {
            $rules['staffPassword'] = ['nullable', 'string', 'min:12'];
        }

        $validated = $this->validate($rules);

        if ($this->editingStaffId) {
            $user = $this->staffQuery()->findOrFail($this->editingStaffId);
            $user->update([
                'name' => $validated['staffName'],
                'email' => $validated['staffEmail'],
                'branch_id' => $validated['staffBranchId'] ?: null,
            ]);
            if ($validated['staffPassword'] !== '') {
                $user->update(['password' => Hash::make($validated['staffPassword'])]);
            }
            $staffAdmin->setActive($user, $validated['staffActive']);
            $staffAdmin->syncRoles($this->business, $user, $validated['staffRoles']);
            $this->toastSuccess(__('operations.staff_updated'));
        } else {
            $staffAdmin->createUser(
                $this->business,
                $validated['staffName'],
                $validated['staffEmail'],
                $validated['staffPassword'],
                $validated['staffBranchId'] ?: null,
                $validated['staffRoles'],
            );
            $this->toastSuccess(__('operations.staff_created'));
        }

        $this->showStaffForm = false;
        $this->resetStaffForm();
    }

    public function startBranch(?int $id = null): void
    {
        $this->resetBranchForm();
        $this->showBranchForm = true;
        if ($id !== null) {
            $branch = $this->branchQuery()->findOrFail($id);
            $this->editingBranchId = $branch->id;
            $this->branchCode = $branch->code;
            $this->branchName = $branch->name;
            $this->branchNameAr = (string) $branch->name_ar;
            $this->branchAddress = (string) $branch->address;
            $settings = (array) ($branch->settings ?? []);
            $contact = $branch->contact();
            $this->branchPhone = (string) ($contact['phone'] ?? '');
            $this->branchEmail = (string) ($contact['email'] ?? '');
            $this->branchCity = (string) ($contact['city'] ?? '');
            $this->branchLat = (string) ($contact['lat'] ?? '');
            $this->branchLng = (string) ($contact['lng'] ?? '');
            $this->branchMapsUrl = (string) ($settings['maps_url'] ?? '');
            $this->branchActive = (bool) $branch->is_active;
        }
    }

    public function saveBranch(): void
    {
        $validated = $this->validate([
            'branchCode' => ['required', 'string', 'max:32', Rule::unique('branches', 'code')->ignore($this->editingBranchId)],
            'branchName' => ['required', 'string', 'max:160'],
            'branchNameAr' => ['nullable', 'string', 'max:160'],
            'branchAddress' => ['nullable', 'string', 'max:500'],
            'branchPhone' => ['nullable', 'string', 'max:40'],
            'branchEmail' => ['nullable', 'email', 'max:255'],
            'branchCity' => ['nullable', 'string', 'max:120'],
            'branchLat' => ['nullable', 'numeric'],
            'branchLng' => ['nullable', 'numeric'],
            'branchMapsUrl' => ['nullable', 'url', 'max:500'],
            'branchActive' => ['boolean'],
        ]);

        $lat = $validated['branchLat'] !== null && $validated['branchLat'] !== ''
            ? (string) round((float) $validated['branchLat'], 6) : '';
        $lng = $validated['branchLng'] !== null && $validated['branchLng'] !== ''
            ? (string) round((float) $validated['branchLng'], 6) : '';
        $mapsUrl = trim((string) ($validated['branchMapsUrl'] ?? ''));
        if ($mapsUrl === '' && $lat !== '' && $lng !== '') {
            $mapsUrl = GeoMapsUrl::googleQuery($lat, $lng);
        }

        $settings = [];
        if ($validated['branchPhone'] !== '') {
            $settings['contact_phone'] = $validated['branchPhone'];
        }
        if ($validated['branchEmail'] !== '') {
            $settings['contact_email'] = $validated['branchEmail'];
        }
        if ($validated['branchCity'] !== '') {
            $settings['city'] = $validated['branchCity'];
        }
        if ($lat !== '') {
            $settings['lat'] = $lat;
        }
        if ($lng !== '') {
            $settings['lng'] = $lng;
        }
        if ($mapsUrl !== '') {
            $settings['maps_url'] = $mapsUrl;
        }

        $payload = [
            'code' => $validated['branchCode'],
            'name' => $validated['branchName'],
            'name_ar' => $validated['branchNameAr'] ?: null,
            'address' => $validated['branchAddress'] ?: null,
            'is_active' => $validated['branchActive'],
            'settings' => $settings !== [] ? $settings : null,
        ];

        if ($this->editingBranchId) {
            $branch = $this->branchQuery()->findOrFail($this->editingBranchId);
            $mergedSettings = array_merge((array) ($branch->settings ?? []), $settings);
            foreach (['contact_phone', 'contact_email', 'city', 'lat', 'lng', 'maps_url'] as $key) {
                if (! isset($settings[$key])) {
                    unset($mergedSettings[$key]);
                }
            }
            $payload['settings'] = $mergedSettings !== [] ? $mergedSettings : null;
            $branch->update($payload);
            $this->toastSuccess(__('operations.branch_updated'));
        } else {
            $this->branchQuery()->create($payload);
            $this->toastSuccess(__('operations.branch_created'));
        }

        $this->showBranchForm = false;
        $this->resetBranchForm();
    }

    public function deactivateBranch(int $id, BranchAdminService $branches): void
    {
        $branch = $this->branchQuery()->findOrFail($id);
        $branches->setActive($branch, false);
        $this->toastSuccess(__('operations.branch_deactivated'));
    }

    public function reactivateBranch(int $id, BranchAdminService $branches): void
    {
        $branch = $this->branchQuery()->findOrFail($id);
        $branches->setActive($branch, true);
        $this->toastSuccess(__('operations.branch_reactivated'));
    }

    public function deleteBranch(int $id, BranchAdminService $branches): void
    {
        $branch = $this->branchQuery()->findOrFail($id);

        if (! $branches->canDelete($branch)) {
            $this->toastError($branches->userFacingDeletionMessage($branch));
            $this->toastError(__('operations.delete_suggest_deactivate'));

            return;
        }

        $branches->delete($branch);
        if ($this->shiftBranchId === $id) {
            $this->shiftBranchId = $this->branchQuery()->orderBy('name')->value('id');
        }
        $this->toastSuccess(__('operations.branch_deleted'));
    }

    public function openShift(ShiftService $shifts): void
    {
        $branch = $this->branchQuery()->findOrFail((int) $this->shiftBranchId);
        if (! $branch->is_active) {
            $this->toastError(__('operations.cannot_open_shift_inactive'));

            return;
        }
        $this->validate(['openingFloat' => ['required', 'numeric', 'min:0']]);

        $user = Auth::guard('staff')->user();
        if (! $user instanceof StaffUser) {
            abort(403);
        }

        $shifts->openShift($branch, $user, (float) $this->openingFloat);
        $this->openingFloat = '';
        $this->toastSuccess(__('operations.shift_opened'));
    }

    public function closeShift(ShiftService $shifts): void
    {
        $branch = $this->branchQuery()->findOrFail((int) $this->shiftBranchId);
        $this->validate(['closingCounted' => ['required', 'numeric', 'min:0']]);

        $open = $shifts->openShiftForBranch($branch);
        if ($open === null) {
            $this->toastError(__('operations.no_open_shift'));

            return;
        }

        $shifts->closeShift($open, (float) $this->closingCounted, $this->closeNotes !== '' ? $this->closeNotes : null);
        $this->closingCounted = '';
        $this->closeNotes = '';
        $this->toastSuccess(__('operations.shift_closed'));
    }

    public function render(ShiftService $shifts): View
    {
        $staff = $this->staffQuery()->with('roles', 'branch')->orderBy('name')->get();
        $branches = $this->branchQuery()->orderBy('name')->get();
        $shiftBranch = $this->shiftBranchId
            ? $this->branchQuery()->find($this->shiftBranchId)
            : null;
        $openShift = $shiftBranch ? $shifts->openShiftForBranch($shiftBranch) : null;
        $recentShifts = $shiftBranch
            ? \App\Models\Shift::query()
                ->withoutGlobalScopes()
                ->where('branch_id', $shiftBranch->id)
                ->orderByDesc('started_at')
                ->limit(8)
                ->get()
            : collect();

        $branchAdmin = app(BranchAdminService::class);
        $branchDeletable = [];
        foreach ($branches as $branch) {
            $branchDeletable[$branch->id] = $branchAdmin->canDelete($branch);
        }

        return view('livewire.operations.team-hub', [
            'staff' => $staff,
            'branches' => $branches,
            'branchDeletable' => $branchDeletable,
            'openShift' => $openShift,
            'recentShifts' => $recentShifts,
            'roleOptions' => ['owner', 'manager', 'cashier', 'accountant'],
        ]);
    }

    private function staffQuery()
    {
        return StaffUser::query();
    }

    private function branchQuery()
    {
        return Branch::query();
    }

    private function resetStaffForm(): void
    {
        $this->editingStaffId = null;
        $this->staffName = '';
        $this->staffEmail = '';
        $this->staffPassword = '';
        $this->staffBranchId = null;
        $this->staffRoles = ['cashier'];
        $this->staffActive = true;
    }

    private function resetBranchForm(): void
    {
        $this->editingBranchId = null;
        $this->branchCode = '';
        $this->branchName = '';
        $this->branchNameAr = '';
        $this->branchAddress = '';
        $this->branchPhone = '';
        $this->branchEmail = '';
        $this->branchCity = '';
        $this->branchLat = '';
        $this->branchLng = '';
        $this->branchMapsUrl = '';
        $this->branchLocationAdvanced = false;
        $this->branchActive = true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'staffName' => ['required', 'string', 'max:120'],
            'staffEmail' => FieldValidationRules::emailRequired(),
            'staffPassword' => ['nullable', 'string', 'min:12'],
            'branchCode' => ['required', 'string', 'max:32'],
            'branchName' => ['required', 'string', 'max:160'],
            'openingFloat' => ['nullable', 'numeric', 'min:0'],
            'closingCounted' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
