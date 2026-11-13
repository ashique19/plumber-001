<?php

namespace App\Livewire\Pos;

use App\Livewire\Concerns\DispatchesToasts;
use App\Enums\PosPaymentMethod;
use App\Enums\ProductItemType;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\PosOrder;
use App\Models\Product;
use App\Services\Inventory\IngredientStockService;
use App\Services\Operations\ShiftService;
use App\Services\Pos\PosBranchInsights;
use App\Services\Pos\PosCatalogQuery;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class Terminal extends Component
{
    use DispatchesToasts;

    public int $branchId;

    public ?int $categoryId = null;

    /** @var 'service'|'part' */
    public string $catalogKind = 'service';

    public ?int $orderId = null;

    public float $promoDiscount = 0;

    /** @var array<int, list<int>> */
    public array $selectedModifiers = [];

    public string $paymentMethod = 'cash';

    /** Keeps the mobile cart sheet open across Livewire DOM patches. */
    public bool $mobileCartOpen = false;

    /** @var array{line: array<string, mixed>, index: int}|null */
    public ?array $undoLineSnapshot = null;

    public function mount(Branch $branch, PosCatalogQuery $catalog, PosOrderService $orders): void
    {
        $user = Auth::guard('staff')->user();
        if ($user->branch_id !== null && (int) $user->branch_id !== (int) $branch->id) {
            abort(403, 'Branch not assigned to cashier.');
        }

        $this->branchId = $branch->id;

        $open = PosOrder::query()
            ->where('branch_id', $branch->id)
            ->where('status', \App\Enums\PosOrderStatus::Open)
            ->where('cashier_id', $user->id)
            ->latest('id')
            ->first();

        if ($open === null) {
            $open = $orders->openOrder($branch, $user);
        }

        $this->orderId = $open->id;
        $this->promoDiscount = (float) $open->promo_discount_ex_vat;

        $categories = $catalog->categoriesForBranch($branch);
        $this->categoryId = $categories->first()?->id;

    }

    public function addProduct(int $productId, PosOrderService $orders): void
    {
        try {
            $order = $this->currentOrder();
            $product = Product::query()->findOrFail($productId);
            $modifiers = $this->selectedModifiers[$productId] ?? [];

            $orders->addProduct($order, $product, 1, $modifiers);
            unset($this->selectedModifiers[$productId]);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError($e->getMessage());
        }
    }

    public function toggleModifier(int $productId, int $modifierId): void
    {
        try {
            $current = $this->selectedModifiers[$productId] ?? [];
            if (in_array($modifierId, $current, true)) {
                $current = array_values(array_diff($current, [$modifierId]));
            } else {
                $current[] = $modifierId;
            }
            $this->selectedModifiers[$productId] = $current;
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function removeLine(int $index, PosOrderService $orders): void
    {
        try {
            $line = $this->currentOrder()->line_items[$index] ?? null;
            if ($line === null) {
                return;
            }

            $this->removeLineAt($index, $orders, $line);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function incrementLine(int $index, PosOrderService $orders): void
    {
        try {
            $lines = $this->currentOrder()->line_items;
            $line = $lines[$index] ?? null;
            if ($line === null) {
                return;
            }

            $qty = (int) floor((float) ($line['quantity'] ?? 1));
            if ($qty >= 999) {
                $this->toastWarning(__('pos.qty_clamped_max'), dedupe: false);

                return;
            }

            $result = $orders->changeLineQuantity($this->currentOrder(), $index, 1);
            $this->handleQuantityResult($result, $index);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function decrementLine(int $index, PosOrderService $orders): void
    {
        try {
            $lines = $this->currentOrder()->line_items;
            $line = $lines[$index] ?? null;
            if ($line === null) {
                return;
            }

            $qty = (int) floor((float) ($line['quantity'] ?? 1));
            if ($qty <= 1) {
                $this->removeLineAt($index, $orders, $line);

                return;
            }

            $result = $orders->changeLineQuantity($this->currentOrder(), $index, -1);
            $this->handleQuantityResult($result, $index, false);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function applyLineQuantity(int $index, mixed $quantity, PosOrderService $orders): void
    {
        try {
            if ($index < 0) {
                return;
            }

            if (! is_numeric($quantity)) {
                $this->toastWarning(__('pos.qty_out_of_range'));

                return;
            }

            $lines = $this->currentOrder()->line_items;
            $line = $lines[$index] ?? null;
            if ($line === null) {
                return;
            }

            $currentQty = (int) floor((float) ($line['quantity'] ?? 1));
            $originalQty = (int) floor((float) $quantity);
            $qty = $originalQty;

            if ($qty === $currentQty) {
                $this->dispatch('pos-line-qty-updated', index: $index, quantity: $currentQty);

                return;
            }

            if ($qty <= 0) {
                $this->dispatch('pos-line-qty-updated', index: $index, quantity: $currentQty);

                return;
            }

            $clampedOverMax = $qty > 999;
            if ($clampedOverMax) {
                $qty = 999;
            }

            $result = $orders->setLineQuantity($this->currentOrder(), $index, (float) $qty);
            $this->handleQuantityResult($result, $index, $clampedOverMax);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function undoRemoveLine(PosOrderService $orders): void
    {
        if ($this->undoLineSnapshot === null) {
            return;
        }

        try {
            $order = $this->currentOrder();
            $lines = $order->line_items;
            $line = $this->undoLineSnapshot['line'];
            $insertAt = min(max(0, $this->undoLineSnapshot['index']), count($lines));
            array_splice($lines, $insertAt, 0, [$line]);
            $orders->setLines($order, array_values($lines));
            $this->undoLineSnapshot = null;
            $this->toastSuccess(__('pos.line_restored'));
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function updatedPromoDiscount(PosOrderService $orders): void
    {
        try {
            $orders->setPromoDiscount($this->currentOrder(), (float) $this->promoDiscount);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function pay(PosCheckoutService $checkout): void
    {
        try {
            $method = PosPaymentMethod::from($this->paymentMethod);
            $order = $checkout->pay($this->currentOrder(), $method, Auth::guard('staff')->user());
            $this->redirectRoute('admin.pos.receipt', [
                'order' => $order->id,
                'locale' => app()->getLocale(),
                'paid' => 1,
                'autoprint' => config('pos.auto_print_receipt') ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->toastError($e->getMessage());
        }
    }

    public function newOrder(PosOrderService $orders, PosCatalogQuery $catalog): void
    {
        try {
            $branch = Branch::query()->findOrFail($this->branchId);
            $open = $orders->openOrder($branch, Auth::guard('staff')->user());
            $this->orderId = $open->id;
            $this->promoDiscount = 0;
            $this->undoLineSnapshot = null;
        } catch (\Throwable $e) {
            report($e);
            $this->toastError(__('pos.qty_update_failed'));
        }
    }

    public function selectCatalogKind(string $kind): void
    {
        try {
            if (! in_array($kind, ['service', 'part'], true)) {
                return;
            }

            $this->catalogKind = $kind;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function selectCategory(int $categoryId): void
    {
        try {
            $this->categoryId = $categoryId;
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function render(PosCatalogQuery $catalog, PosBranchInsights $insights, ShiftService $shifts, IngredientStockService $stock): View
    {
        $branch = Branch::query()->findOrFail($this->branchId);
        $business = BusinessSetting::singleton();
        $tradesCatalog = false;
        $activeShift = $shifts->openShiftForBranch($branch);
        $order = $this->currentOrder();
        $categories = $catalog->categoriesForBranch($branch);

        if ($tradesCatalog) {
            $itemType = $this->catalogKind === 'part' ? ProductItemType::Part : ProductItemType::Service;
            $products = $catalog->productsForBranch(null, $branch, $itemType);
        } else {
            $products = $this->categoryId
                ? $catalog->productsForBranch($this->categoryId, $branch)
                : collect();
        }

        $stockStates = [];
        foreach ($products as $product) {
            $stockStates[$product->id] = $stock->availabilityForProduct($product, $branch, 1.0);
        }

        return view('livewire.pos.terminal', [
            'branch' => $branch,
            'business' => $business,
            'tradesCatalog' => $tradesCatalog,
            'order' => $order,
            'categories' => $categories,
            'products' => $products,
            'stockStates' => $stockStates,
            'shift' => $insights->todaySnapshot($branch),
            'activeShift' => $activeShift,
        ]);
    }

    private function currentOrder(): PosOrder
    {
        return PosOrder::query()->findOrFail($this->orderId);
    }

    /**
     * @param  array{order: PosOrder, requested: float, applied: float, capped: bool, removed: bool}  $result
     */
    private function handleQuantityResult(array $result, int $index, bool $clampedOverMax = false): void
    {
        if ($result['removed']) {
            if ($result['capped']) {
                $this->toastWarning(__('pos.stock_only_n_available', ['count' => 0]), dedupe: false);
            }
            $this->dispatch('pos-line-qty-updated', index: $index, quantity: 0);

            return;
        }

        if ($result['applied'] <= 0) {
            return;
        }

        if ($clampedOverMax) {
            $this->toastWarning(__('pos.qty_clamped_max'), dedupe: false);
        }

        if ($result['capped']) {
            $this->toastWarning(__('pos.stock_only_n_available', [
                'count' => (int) $result['applied'],
            ]), dedupe: false);
        }

        $applied = (int) floor($result['applied']);
        $this->undoLineSnapshot = null;
        $this->dispatch('pos-line-qty-updated', index: $index, quantity: max(0, $applied));
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function removeLineAt(int $index, PosOrderService $orders, array $line): void
    {
        $order = $this->currentOrder();
        $lines = $order->line_items;
        if (! array_key_exists($index, $lines)) {
            return;
        }

        $this->undoLineSnapshot = ['line' => $line, 'index' => $index];
        unset($lines[$index]);
        $orders->setLines($order, array_values($lines));
        $this->toastInfo(__('pos.line_removed_toast', ['name' => $line['name'] ?? '']));
        $this->dispatch('pos-line-qty-updated', index: $index, quantity: 0);
    }
}
