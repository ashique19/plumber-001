<?php

namespace App\Livewire\Purchases;

use App\Business\BusinessContext;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseRecordingService;
use App\Support\Inventory\ProductStockSchema;
use App\Support\StaffBranchContext;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class CreatePurchase extends Component
{
    use DispatchesToasts;
    use ResolvesBusinessContext;

    public ?int $supplierId = null;

    public string $supplierName = '';

    public string $supplierVat = '';

    public string $supplierPhone = '';

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

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        abort_unless(auth('staff')->user()?->can('inventory.manage'), 403);
        $this->schemaReady = ProductStockSchema::ready();
    }

    public function addLine(): void
    {
        $this->lines[] = ['product_id' => null, 'quantity' => '1', 'unit_cost' => '', 'vat_rate' => '15'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(PurchaseRecordingService $purchases): void
    {
        if (! $this->schemaReady) {
            $this->toastError(__('purchases.schema_missing'));

            return;
        }

        $validated = $this->validate([
            'supplierId' => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplierName' => ['required_without:supplierId', 'string', 'max:160'],
            'supplierVat' => ['nullable', 'string', 'max:32'],
            'supplierPhone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return;
        }
        StaffBranchContext::remember($branch);

        $user = auth('staff')->user();
        abort_if($user === null, 403);

        $supplier = $validated['supplierId']
            ? Supplier::query()->where('branch_id', $branch->id)->findOrFail($validated['supplierId'])
            : $purchases->findOrCreateSupplier(
                $branch,
                $validated['supplierName'],
                $validated['supplierVat'] ?? null,
                $validated['supplierPhone'] ?? null,
            );

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

        $this->toastSuccess(__('purchases.recorded', ['number' => $purchase->purchase_number]));
        $this->redirect(route('admin.stock.search'), navigate: true);
    }

    public function render(): View
    {
        $branchId = StaffBranchContext::resolve()?->id;

        $suppliers = $this->schemaReady && $branchId
            ? Supplier::query()->where('branch_id', $branchId)->orderBy('name')->get()
            : collect();

        $products = $this->schemaReady
            ? Product::query()->where('is_active', true)->where('track_stock', true)->orderBy('name_en')->get()
            : collect();

        return view('livewire.purchases.create-purchase', [
            'suppliers' => $suppliers,
            'products' => $products,
            'pageTitle' => __('purchases.create_title'),
        ])->layout('layouts.pos', ['pageTitle' => __('purchases.create_title')]);
    }
}
