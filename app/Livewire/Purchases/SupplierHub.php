<?php

namespace App\Livewire\Purchases;

use App\Business\BusinessContext;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Supplier;
use App\Services\Inventory\SupplierService;
use App\Support\Inventory\ProductStockSchema;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class SupplierHub extends Component
{
    use DispatchesToasts;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $supplier = null;

    public bool $showCreateForm = false;

    public string $formName = '';

    public string $formNameAr = '';

    public string $formContactPerson = '';

    public string $formPhone = '';

    public string $formEmail = '';

    public string $formVat = '';

    public string $formCr = '';

    public string $formAddress = '';

    public string $formCity = '';

    public string $formNotes = '';

    public bool $formActive = true;

    public bool $schemaReady = true;

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        abort_unless(auth('staff')->user()?->can('inventory.manage'), 403);
        $this->schemaReady = ProductStockSchema::ready();

        if ($this->supplier !== null) {
            $this->loadSupplier($this->supplier);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->supplier = null;
        $this->showCreateForm = false;
    }

    public function selectSupplier(int $supplierId): void
    {
        $this->supplier = $supplierId;
        $this->showCreateForm = false;
        $this->loadSupplier($supplierId);
    }

    public function startCreate(): void
    {
        $this->supplier = null;
        $this->showCreateForm = true;
        $this->resetForm();
    }

    public function cancelForm(): void
    {
        $this->showCreateForm = false;
        if ($this->supplier !== null) {
            $this->loadSupplier($this->supplier);
        }
    }

    public function saveSupplier(SupplierService $suppliers): void
    {
        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return;
        }

        $rules = $this->fullValidationRules();
        $this->validate($rules);

        $payload = $this->formPayload();

        if ($this->supplier !== null) {
            $model = $this->findBranchSupplier($this->supplier, $branch->id);
            if ($model === null) {
                return;
            }
            $suppliers->update($model, $payload);
            $this->toastSuccess(__('suppliers.updated'));
        } else {
            $model = $suppliers->create($branch, $payload);
            $this->supplier = $model->id;
            $this->showCreateForm = false;
            $this->toastSuccess(__('suppliers.created'));
        }

        $this->loadSupplier($this->supplier);
    }

    public function deleteSupplier(SupplierService $suppliers): void
    {
        $branch = StaffBranchContext::resolve();
        if ($branch === null || $this->supplier === null) {
            return;
        }

        $model = $this->findBranchSupplier($this->supplier, $branch->id);
        if ($model === null) {
            return;
        }

        if ($model->purchases()->exists()) {
            $this->toastError(__('suppliers.delete_blocked'));

            return;
        }

        if (! $suppliers->deleteIfUnused($model)) {
            $this->toastError(__('suppliers.delete_blocked'));

            return;
        }

        $this->supplier = null;
        $this->resetForm();
        $this->showCreateForm = false;
        $this->toastSuccess(__('suppliers.deleted'));
    }

    protected function liveValidationRules(): array
    {
        return [
            'formName' => ['required', 'string', 'max:160'],
            'formNameAr' => ['nullable', 'string', 'max:160'],
            'formContactPerson' => ['nullable', 'string', 'max:120'],
            'formPhone' => FieldValidationRules::saudiMobileOptional(),
            'formEmail' => FieldValidationRules::emailOptional(),
            'formVat' => FieldValidationRules::vatOptional(),
            'formCr' => FieldValidationRules::crNumberOptional(),
            'formAddress' => ['nullable', 'string', 'max:500'],
            'formCity' => ['nullable', 'string', 'max:80'],
            'formNotes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fullValidationRules(): array
    {
        return array_merge($this->liveValidationRules(), [
            'formActive' => ['boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(): array
    {
        return [
            'name' => $this->formName,
            'name_ar' => $this->formNameAr,
            'contact_person' => $this->formContactPerson,
            'phone' => $this->formPhone,
            'email' => $this->formEmail,
            'vat_number' => $this->formVat,
            'cr_number' => $this->formCr,
            'address' => $this->formAddress,
            'city' => $this->formCity,
            'notes' => $this->formNotes,
            'is_active' => $this->formActive,
        ];
    }

    private function loadSupplier(int $supplierId): void
    {
        $branchId = StaffBranchContext::resolve()?->id;
        $model = $this->findBranchSupplier($supplierId, $branchId);
        if ($model === null) {
            return;
        }

        $this->formName = $model->name;
        $this->formNameAr = $model->name_ar ?? '';
        $this->formContactPerson = $model->contact_person ?? '';
        $this->formPhone = $model->phone ?? '';
        $this->formEmail = $model->email ?? '';
        $this->formVat = $model->vat_number ?? '';
        $this->formCr = $model->cr_number ?? '';
        $this->formAddress = $model->address ?? '';
        $this->formCity = $model->city ?? '';
        $this->formNotes = $model->notes ?? '';
        $this->formActive = (bool) $model->is_active;
    }

    private function resetForm(): void
    {
        $this->formName = '';
        $this->formNameAr = '';
        $this->formContactPerson = '';
        $this->formPhone = '';
        $this->formEmail = '';
        $this->formVat = '';
        $this->formCr = '';
        $this->formAddress = '';
        $this->formCity = '';
        $this->formNotes = '';
        $this->formActive = true;
    }

    private function findBranchSupplier(int $supplierId, ?int $branchId): ?Supplier
    {
        if ($branchId === null) {
            return null;
        }

        return Supplier::query()
            ->where('branch_id', $branchId)
            ->whereKey($supplierId)
            ->first();
    }

    private function selectedSupplier(): ?Supplier
    {
        if ($this->supplier === null) {
            return null;
        }

        return $this->findBranchSupplier($this->supplier, StaffBranchContext::resolve()?->id);
    }

    public function render(): View
    {
        $branchId = StaffBranchContext::resolve()?->id;
        $selected = $this->selectedSupplier();
        $purchaseCount = $selected?->purchases()->count() ?? 0;

        /** @var LengthAwarePaginator<int, Supplier>|null $page */
        $page = null;

        if ($this->schemaReady && $branchId !== null) {
            $query = Supplier::query()->where('branch_id', $branchId);
            $term = trim($this->search);
            if ($term !== '') {
                $like = '%'.$term.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('name_ar', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('vat_number', 'like', $like);
                });
            }

            $page = $query->orderBy('name')->paginate(20);
        }

        return view('livewire.purchases.supplier-hub', [
            'suppliers' => $page,
            'selected' => $selected,
            'purchaseCount' => $purchaseCount,
            'pageTitle' => __('suppliers.title'),
        ])->layout('layouts.pos', ['pageTitle' => __('suppliers.title')]);
    }
}