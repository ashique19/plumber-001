<?php

namespace App\Services\Pos;

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\ProductModifier;
use App\Exceptions\InsufficientIngredientStockException;
use App\Models\StaffUser;
use App\Services\Inventory\IngredientStockService;
use Illuminate\Support\Str;

class PosOrderService
{
    public function __construct(
        private readonly PosTotalsCalculator $totals,
        private readonly IngredientStockService $ingredientStock,
    ) {}

    public function openOrder(Branch $branch, StaffUser $cashier): PosOrder
    {
        $this->assertBranchAccess($cashier, $branch);

        return PosOrder::query()->create([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'order_number' => $this->nextOrderNumber($branch),
            'status' => PosOrderStatus::Open,
            'line_items' => [],
            'subtotal_ex_vat' => 0,
            'discount_ex_vat' => 0,
            'promo_discount_ex_vat' => 0,
            'loyalty_discount_ex_vat' => 0,
            'vat_total' => 0,
            'total_inc_vat' => 0,
        ]);
    }

    /**
     * @param  list<int>  $modifierIds
     */
    public function addProduct(PosOrder $order, Product $product, float $quantity = 1, array $modifierIds = []): PosOrder
    {
        $this->assertOpen($order);

        $lines = $order->line_items;

        foreach ($lines as $index => $existing) {
            if (($existing['product_id'] ?? null) !== $product->id) {
                continue;
            }
            $existingMods = array_values(array_map('intval', $existing['modifier_ids'] ?? []));
            $newMods = array_values(array_map('intval', $modifierIds));
            sort($existingMods);
            sort($newMods);
            if ($existingMods === $newMods) {
                $lines[$index]['quantity'] = (float) ($existing['quantity'] ?? 1) + $quantity;
                $this->assertProductStock($order, $product, (float) $lines[$index]['quantity']);

                return $this->persistLines($order, $lines);
            }
        }

        $modifiers = ProductModifier::query()
            ->whereIn('id', $modifierIds)
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->get();

        $modifierEx = $modifiers->sum(fn (ProductModifier $m) => (float) $m->price_ex_vat);
        $unitPrice = (float) $product->price_ex_vat + $modifierEx;

        $lines[] = [
            'product_id' => $product->id,
            'item_type' => $product->itemType()->value,
            'name' => $product->name_en,
            'name_ar' => $product->name_ar,
            'quantity' => $quantity,
            'unit_price_ex_vat' => $unitPrice,
            'vat_rate' => (float) $product->vat_rate,
            'discount_ex_vat' => 0,
            'modifier_ids' => $modifiers->pluck('id')->all(),
            'modifier_names' => $modifiers->pluck('name_en')->all(),
        ];

        $this->assertProductStock($order, $product, (float) $quantity);

        return $this->persistLines($order, $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function setLines(PosOrder $order, array $lines): PosOrder
    {
        $this->assertOpen($order);

        return $this->persistLines($order, $lines);
    }

    /**
     * @return array{order: PosOrder, requested: float, applied: float, capped: bool, removed: bool}
     */
    public function setLineQuantity(PosOrder $order, int $lineIndex, float $quantity): array
    {
        $this->assertOpen($order);

        $requested = $quantity;
        $lines = $order->line_items;
        $line = $lines[$lineIndex] ?? null;
        if ($line === null) {
            return [
                'order' => $order,
                'requested' => $requested,
                'applied' => 0,
                'capped' => false,
                'removed' => false,
            ];
        }

        if ($quantity <= 0) {
            unset($lines[$lineIndex]);

            return [
                'order' => $this->persistLines($order, array_values($lines)),
                'requested' => $requested,
                'applied' => 0,
                'capped' => false,
                'removed' => true,
            ];
        }

        $quantity = min(999, max(1, $quantity));
        $capped = false;

        $productId = (int) ($line['product_id'] ?? 0);
        if ($productId > 0) {
            $product = Product::query()->find($productId);
            if ($product !== null) {
                $order->loadMissing('branch');
                $maxUnits = $this->ingredientStock->maxSellableUnitsForOrderLine(
                    $order,
                    $lineIndex,
                    $product,
                    $order->branch,
                );
                if ($maxUnits !== null && $quantity > $maxUnits) {
                    $quantity = max(0, (float) $maxUnits);
                    $capped = true;
                }
            }
        }

        if ($quantity < 1) {
            unset($lines[$lineIndex]);

            return [
                'order' => $this->persistLines($order, array_values($lines)),
                'requested' => $requested,
                'applied' => 0,
                'capped' => $capped || $requested > 0,
                'removed' => true,
            ];
        }

        $lines[$lineIndex]['quantity'] = $quantity;

        if ($productId > 0) {
            $product = Product::query()->find($productId);
            if ($product !== null) {
                try {
                    $this->assertProductStock($order, $product, $quantity);
                } catch (\Throwable $e) {
                    report($e);
                    $maxUnits = $this->ingredientStock->maxSellableUnitsForOrderLine(
                        $order,
                        $lineIndex,
                        $product,
                        $order->branch,
                    );
                    if ($maxUnits !== null && $maxUnits >= 1 && $maxUnits < $quantity) {
                        $quantity = (float) $maxUnits;
                        $capped = true;
                        $lines[$lineIndex]['quantity'] = $quantity;
                    } else {
                        throw $e;
                    }
                }
            }
        }

        return [
            'order' => $this->persistLines($order, $lines),
            'requested' => $requested,
            'applied' => $quantity,
            'capped' => $capped || $requested > $quantity,
            'removed' => false,
        ];
    }

    /**
     * @return array{order: PosOrder, requested: float, applied: float, capped: bool, removed: bool}
     */
    public function changeLineQuantity(PosOrder $order, int $lineIndex, int $delta): array
    {
        $lines = $order->line_items;
        $line = $lines[$lineIndex] ?? null;
        if ($line === null) {
            return [
                'order' => $order,
                'requested' => 0,
                'applied' => 0,
                'capped' => false,
                'removed' => false,
            ];
        }

        $current = (float) ($line['quantity'] ?? 1);
        $next = (int) floor($current) + $delta;

        return $this->setLineQuantity($order, $lineIndex, (float) $next);
    }

    public function setPromoDiscount(PosOrder $order, float $promoExVat): PosOrder
    {
        $this->assertOpen($order);

        $order->promo_discount_ex_vat = max(0, $promoExVat);
        $order->discount_ex_vat = (float) $order->promo_discount_ex_vat + (float) $order->loyalty_discount_ex_vat;

        return $this->recalculateAndSave($order);
    }

    public function applyLoyaltyDiscount(PosOrder $order, float $loyaltyExVat, int $pointsRedeemed): PosOrder
    {
        $this->assertOpen($order);

        $order->forceFill([
            'loyalty_discount_ex_vat' => max(0, $loyaltyExVat),
            'loyalty_points_redeemed' => max(0, $pointsRedeemed),
            'discount_ex_vat' => (float) $order->promo_discount_ex_vat + max(0, $loyaltyExVat),
        ]);

        return $this->recalculateAndSave($order);
    }

    public function cancel(PosOrder $order): PosOrder
    {
        $this->assertOpen($order);

        $order->forceFill([
            'status' => PosOrderStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        return $order->fresh();
    }

    private function persistLines(PosOrder $order, array $lines): PosOrder
    {
        $order->line_items = array_values($lines);

        return $this->recalculateAndSave($order);
    }

    private function recalculateAndSave(PosOrder $order): PosOrder
    {
        $promoAndLoyalty = (float) $order->promo_discount_ex_vat + (float) $order->loyalty_discount_ex_vat;
        $totals = $this->totals->fromLines(
            $order->line_items,
            $promoAndLoyalty,
        );

        $order->forceFill([
            'subtotal_ex_vat' => $totals['subtotal_ex_vat'],
            'vat_total' => $totals['vat_total'],
            'total_inc_vat' => $totals['total_inc_vat'],
            'discount_ex_vat' => (float) $order->promo_discount_ex_vat + (float) $order->loyalty_discount_ex_vat,
        ])->save();

        return $order->fresh();
    }

    private function nextOrderNumber(Branch $branch): string
    {
        $seq = PosOrder::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $branch->tenant_id)
            ->whereDate('created_at', today())
            ->count() + 1;

        return $branch->code.'-'.now()->format('Ymd').'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function assertProductStock(PosOrder $order, Product $product, float $lineQty): void
    {
        $order->loadMissing('branch');
        $state = $this->ingredientStock->availabilityForProduct($product, $order->branch, $lineQty);
        if ($state === 'out') {
            throw new InsufficientIngredientStockException(__('inventory.error_out_of_stock_product'));
        }
    }

    private function assertOpen(PosOrder $order): void
    {
        if ($order->status !== PosOrderStatus::Open) {
            throw new \InvalidArgumentException('Only open orders can be modified.');
        }
    }

    private function assertBranchAccess(StaffUser $cashier, Branch $branch): void
    {
        if ($cashier->branch_id !== null && (int) $cashier->branch_id !== (int) $branch->id) {
            throw new \InvalidArgumentException('Cashier is restricted to another branch.');
        }
    }
}
