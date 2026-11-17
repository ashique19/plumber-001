<?php

namespace App\Livewire\Purchases;

use App\Business\BusinessContext;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ManagesPurchaseCatalogPicker;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseRecordingService;
use App\Services\Inventory\SupplierService;
use App\Support\Hub\HubFormSubmissionIdempotency;
use App\Support\Inventory\ProductStockSchema;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use App\Support\Validation\QuickModalValidationAttributes;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class CreatePurchase extends Component
{
    use DispatchesToasts;
    use ManagesPurchaseCatalogPicker;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    public ?int $supplierId = null;

    public string $supplierSearch = '';

    public bool $showQuickSupplierModal = false;

    public string $quickSupplierName = '';

    public string $quickSupplierNameAr = '';

    public string $quickSupplierPhone = '';

    public string $quickSupplierVat = '';

    public string $notes = '';

    /**
     * @var list<array{
     *     product_id: int|null,
     *     quantity: string,
     *     unit_cost: string,
     *     vat_rate: string
     * }>
     */
    public array $lines = [
        ['product_id' => null, 'quantity' => '1', 'unit_cost' => '', 'vat_rate' => '15'],
    ];

    public bool $schemaReady = true;

    #[Locked]
    public string $formSubmissionToken = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        abort_unless(auth('staff')->user()?->can('inventory.manage'), 403);
        $this->schemaReady = ProductStockSchema::ready();
        $this->formSubmissionToken = (string) Str::uuid();
    }

    protected function liveValidationRules(): array
    {
        return [
            'quickSupplierName' => ['required', 'string', 'max:160'],
            'quickSupplierNameAr' => ['nullable', 'string', 'max:160'],
            'quickSupplierPhone' => FieldValidationRules::saudiMobileOptional(),
            'quickSupplierVat' => FieldValidationRules::vatOptional(),
        ];
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => null, 'quantity' => '1', 'unit_cost' => '', 'vat_rate' => '15'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            $this->lines = [
                ['product_id' => null, 'quantity' => '1', 'unit_cost' => '', 'vat_rate' => '15'],
            ];

            return;
        }

        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function selectPurchaseSupplier(int $supplierId): void
    {
        $branchId = StaffBranchContext::resolve()?->id;
        if ($branchId === null) {
            return;
        }

        $exists = Supplier::query()->where('branch_id', $branchId)->active()->whereKey($supplierId)->exists();
        if (! $exists) {
            return;
        }

        $this->supplierId = $supplierId;
        $this->supplierSearch = '';
    }

    public function clearSelectedSupplier(): void
    {
        $this->supplierId = null;
    }

    public function clearSupplierSearch(): void
    {
        $this->supplierSearch = '';
    }

    public function openQuickSupplierModal(): void
    {
        $this->showQuickSupplierModal = true;
    }

    public function saveQuickSupplier(SupplierService $suppliers): void
    {
        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return;
        }

        $this->validate([
            'quickSupplierName' => ['required', 'string', 'max:160'],
            'quickSupplierNameAr' => ['nullable', 'string', 'max:160'],
            'quickSupplierPhone' => FieldValidationRules::saudiMobileOptional(),
            'quickSupplierVat' => FieldValidationRules::vatOptional(),
        ]);

        $supplier = $suppliers->create($branch, [
            'name' => $this->quickSupplierName,
            'name_ar' => $this->quickSupplierNameAr,
            'phone' => $this->quickSupplierPhone,
            'vat_number' => $this->quickSupplierVat,
            'is_active' => true,
        ]);

        $this->supplierId = $supplier->id;
        $this->showQuickSupplierModal = false;
        $this->supplierSearch = '';
        $this->quickSupplierName = '';
        $this->quickSupplierNameAr = '';
        $this->quickSupplierPhone = '';
        $this->quickSupplierVat = '';
        $this->toastSuccess(__('suppliers.created'));
    }

    public function save(PurchaseRecordingService $purchases): void
    {
        if (! $this->schemaReady) {
            $this->toastError(__('purchases.schema_missing'));

            return;
        }

        $this->lines = array_values(array_filter(
            $this->lines,
            fn (array $row): bool => filled($row['product_id'] ?? null),
        ));

        if ($this->lines === []) {
            $this->addError('document', __('invoices.errors.lines_required'));
            $this->dispatch('document-validation-failed');

            return;
        }

        try {
            $validated = $this->validate([
                'supplierId' => ['required', 'integer', 'exists:suppliers,id'],
                'notes' => ['nullable', 'string', 'max:2000'],
                'lines' => ['required', 'array', 'min:1'],
                'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
                'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
                'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
                'lines.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->dispatch('document-validation-failed');

            throw $exception;
        }

        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return;
        }
        StaffBranchContext::remember($branch);

        $user = auth('staff')->user();
        abort_if($user === null, 403);

        $actorId = $user->id;
        $scope = 'purchase_save';
        if (HubFormSubmissionIdempotency::storedResourceId($scope, $this->formSubmissionToken, $actorId) !== null) {
            $this->redirect(route('admin.stock.search'), navigate: true);

            return;
        }

        $lock = HubFormSubmissionIdempotency::acquireLock($scope, $this->formSubmissionToken, $actorId);
        if ($lock === null) {
            if (HubFormSubmissionIdempotency::waitForStoredResourceId($scope, $this->formSubmissionToken, $actorId) !== null) {
                $this->redirect(route('admin.stock.search'), navigate: true);
            }

            return;
        }

        try {
            if (HubFormSubmissionIdempotency::storedResourceId($scope, $this->formSubmissionToken, $actorId) !== null) {
                $this->redirect(route('admin.stock.search'), navigate: true);

                return;
            }

        $supplier = Supplier::query()
            ->where('branch_id', $branch->id)
            ->whereKey($validated['supplierId'])
            ->firstOrFail();

        $linePayload = [];
        foreach ($validated['lines'] as $row) {
            $linePayload[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => (float) $row['quantity'],
                'unit_cost_ex_vat' => (float) $row['unit_cost'],
                'vat_rate' => (float) $row['vat_rate'],
            ];
        }

        $purchase = $purchases->record($branch, $supplier, $linePayload, $validated['notes'] ?? null, $user);

        HubFormSubmissionIdempotency::rememberResourceId($scope, $this->formSubmissionToken, $actorId, (int) $purchase->id);

        $this->toastSuccess(__('purchases.recorded', ['number' => $purchase->purchase_number]));
        $this->redirect(route('admin.stock.search'), navigate: true);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return QuickModalValidationAttributes::map();
    }

    public function render(): View
    {
        $branchId = StaffBranchContext::resolve()?->id;
        $supplierService = app(SupplierService::class);

        $supplierHits = $this->schemaReady && $branchId !== null
            ? $supplierService->search($branchId, $this->supplierSearch)
            : collect();

        $selectedSupplier = $this->supplierId && $branchId
            ? Supplier::query()->where('branch_id', $branchId)->find($this->supplierId)
            : null;

        $productHits = $this->schemaReady ? $this->purchaseProductSearchResults() : collect();

        $productIds = collect($this->lines)->pluck('product_id')->filter()->unique()->values();
        $lineProducts = $productIds->isEmpty()
            ? collect()
            : Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        $productCategories = ProductCategory::query()->orderBy('name_en')->get();

        return view('livewire.purchases.create-purchase', [
            'supplierHits' => $supplierHits,
            'selectedSupplier' => $selectedSupplier,
            'productHits' => $productHits,
            'lineProducts' => $lineProducts,
            'productCategories' => $productCategories,
            'canCreateProduct' => $this->canCreateProductsFromPurchase(),
            'pageTitle' => __('purchases.create_title'),
        ])->layout('layouts.pos', ['pageTitle' => __('purchases.create_title')]);
    }
}