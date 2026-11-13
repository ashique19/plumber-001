<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientIngredientStockException;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\InventoryMovement;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\ProductIngredient;
use App\Models\Recipe;
use App\Models\BusinessSettingUser;
use App\Support\Inventory\IngredientInventorySchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IngredientStockService
{
    /** @return Collection<int, array{ingredient_id: int, quantity_per_unit: float}> */
    public function billOfMaterials(int $productId): Collection
    {
        if (! IngredientInventorySchema::fullReady()) {
            return collect();
        }

        $rows = ProductIngredient::query()
            ->where('product_id', $productId)
            ->get(['ingredient_id', 'quantity_per_unit']);

        if ($rows->isNotEmpty()) {
            return $rows->map(fn (ProductIngredient $row) => [
                'ingredient_id' => (int) $row->ingredient_id,
                'quantity_per_unit' => (float) $row->quantity_per_unit,
            ]);
        }

        $recipe = Recipe::query()->where('product_id', $productId)->with('lines')->first();
        if ($recipe === null) {
            return collect();
        }

        return $recipe->lines->map(fn ($row) => [
            'ingredient_id' => (int) $row->ingredient_id,
            'quantity_per_unit' => (float) $row->quantity,
        ]);
    }

    /**
     * @return 'ok'|'low'|'out'|null null = no BOM / inventory not enabled
     */
    public function availabilityForProduct(Product $product, ?Branch $branch, float $requestedQty = 1.0): ?string
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return null;
        }

        $bom = $this->billOfMaterials($product->id);
        if ($bom->isEmpty()) {
            return null;
        }

        $worst = 'ok';

        foreach ($bom as $line) {
            $ingredient = $this->resolveIngredient($branch, (int) $line['ingredient_id']);
            if ($ingredient === null) {
                return 'out';
            }
            if (Schema::hasColumn('ingredients', 'is_active') && ! $ingredient->is_active) {
                return 'out';
            }

            $need = (float) $line['quantity_per_unit'] * $requestedQty;
            $onHand = (float) $ingredient->quantity_on_hand;

            if ($onHand <= 0 || $onHand < $need) {
                return 'out';
            }

            if ($ingredient->isLowStock()) {
                $worst = 'low';
            }
        }

        return $worst;
    }

    /** Maximum whole units sellable from BOM stock, or null when inventory/BOM does not apply. */
    public function maxSellableUnits(Product $product, ?Branch $branch): ?int
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return null;
        }

        $bom = $this->billOfMaterials($product->id);
        if ($bom->isEmpty()) {
            return null;
        }

        $cap = null;

        foreach ($bom as $line) {
            $ingredient = $this->resolveIngredient($branch, (int) $line['ingredient_id']);
            if ($ingredient === null) {
                return 0;
            }

            $perUnit = (float) $line['quantity_per_unit'];
            if ($perUnit <= 0) {
                continue;
            }

            $units = (int) floor((float) $ingredient->quantity_on_hand / $perUnit);
            $cap = $cap === null ? $units : min($cap, $units);
        }

        return $cap === null ? null : max(0, $cap);
    }

    /** Max units for one cart line after other lines' BOM usage, or null when BOM does not apply. */
    public function maxSellableUnitsForOrderLine(PosOrder $order, int $lineIndex, Product $product, ?Branch $branch): ?int
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return null;
        }

        $bom = $this->billOfMaterials($product->id);
        if ($bom->isEmpty()) {
            return null;
        }

        $requiredElsewhere = $this->requiredTotalsForOrder($order);
        $line = $order->line_items[$lineIndex] ?? null;
        if (is_array($line)) {
            $currentQty = (float) ($line['quantity'] ?? 0);
            foreach ($bom as $bomLine) {
                $ingredientId = (int) $bomLine['ingredient_id'];
                $requiredElsewhere[$ingredientId] = ($requiredElsewhere[$ingredientId] ?? 0)
                    - ((float) $bomLine['quantity_per_unit'] * $currentQty);
            }
        }

        $cap = null;

        foreach ($bom as $bomLine) {
            $ingredient = $this->resolveIngredient($branch, (int) $bomLine['ingredient_id']);
            if ($ingredient === null) {
                return 0;
            }

            $perUnit = (float) $bomLine['quantity_per_unit'];
            if ($perUnit <= 0) {
                continue;
            }

            $ingredientId = (int) $ingredient->id;
            $reserved = max(0, (float) ($requiredElsewhere[$ingredientId] ?? 0));
            $available = max(0, (float) $ingredient->quantity_on_hand - $reserved);
            $units = (int) floor($available / $perUnit);
            $cap = $cap === null ? $units : min($cap, $units);
        }

        return $cap === null ? null : max(0, $cap);
    }

    /**
     * @return array<int, float> ingredient_id => total required qty
     */
    public function requiredTotalsForOrder(PosOrder $order): array
    {
        $totals = [];

        foreach ($order->line_items as $line) {
            $productId = $line['product_id'] ?? null;
            if ($productId === null) {
                continue;
            }
            $qty = (float) ($line['quantity'] ?? 1);
            foreach ($this->billOfMaterials((int) $productId) as $bom) {
                $id = $bom['ingredient_id'];
                $totals[$id] = ($totals[$id] ?? 0) + $bom['quantity_per_unit'] * $qty;
            }
        }

        return $totals;
    }

    public function assertOrderFulfillable(PosOrder $order, ?Branch $branch): void
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return;
        }

        $branch = $branch ?? $order->branch;
        $totals = $this->requiredTotalsForOrder($order);
        if ($totals === []) {
            return;
        }

        foreach ($totals as $ingredientId => $need) {
            $ingredient = $this->resolveIngredient($branch, (int) $ingredientId);
            if ($ingredient === null) {
                throw new InsufficientIngredientStockException(__('inventory.error_missing_ingredient'));
            }
            if ((float) $ingredient->quantity_on_hand < $need) {
                throw new InsufficientIngredientStockException(
                    __('inventory.error_insufficient_stock', ['name' => $ingredient->displayName()])
                );
            }
        }
    }

    public function deductForPaidOrder(PosOrder $order, ?int $userId = null): void
    {
        if (! IngredientInventorySchema::fullReady()) {
            return;
        }

        $totals = $this->requiredTotalsForOrder($order);
        if ($totals === []) {
            return;
        }

        foreach ($totals as $ingredientId => $delta) {
            $ingredient = Ingredient::query()->lockForUpdate()->find($ingredientId);
            if ($ingredient === null) {
                continue;
            }

            $ingredient->decrement('quantity_on_hand', $delta);
            $this->logMovement($ingredient, -$delta, 'order', $order, $userId);
        }
    }

    public function restoreForRefundedOrder(PosOrder $order, ?int $userId = null): void
    {
        if (! IngredientInventorySchema::fullReady()) {
            return;
        }

        $totals = $this->requiredTotalsForOrder($order);
        if ($totals === []) {
            return;
        }

        foreach ($totals as $ingredientId => $delta) {
            $ingredient = Ingredient::query()->lockForUpdate()->find($ingredientId);
            if ($ingredient === null) {
                continue;
            }

            $ingredient->increment('quantity_on_hand', $delta);
            $this->logMovement($ingredient, $delta, 'refund', $order, $userId);
        }
    }

    public function adjustStock(Ingredient $ingredient, float $change, string $mode, StaffUser $user, ?Branch $branch = null): void
    {
        if (! IngredientInventorySchema::movementsReady()) {
            return;
        }

        DB::transaction(function () use ($ingredient, $change, $mode, $user, $branch) {
            $locked = Ingredient::query()->lockForUpdate()->findOrFail($ingredient->id);
            $delta = match ($mode) {
                'set' => $change - (float) $locked->quantity_on_hand,
                'remove' => -abs($change),
                default => abs($change),
            };

            $locked->increment('quantity_on_hand', $delta);

            $this->logMovement($locked, $delta, 'adjust', null, $user->id, $branch);
        });
    }

    /** @return list<Ingredient> */
    public function lowStockForTenant(?Branch $branch = null, int $limit = 12): array
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return [];
        }

        return $this->ingredientsQuery($branch)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Ingredient $ing) => $ing->isLowStock())
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return Collection<int, Product> */
    public function soldOutProductIds(?Branch $branch, Collection $products): Collection
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return collect();
        }

        return $products->filter(function (Product $product) use ($branch) {
            return $this->availabilityForProduct($product, $branch, 1.0) === 'out';
        });
    }

    private function ingredientsQuery(?Branch $branch): Builder
    {
        return Ingredient::query()
            
            ->where(function (Builder $q) use ($branch) {
                $q->whereNull('branch_id');
                if ($branch !== null) {
                    $q->orWhere('branch_id', $branch->id);
                }
            });
    }

    private function resolveIngredient(?Branch $branch, int $ingredientId): ?Ingredient
    {
        $row = Ingredient::query()
            
            ->where('id', $ingredientId)
            ->where(function (Builder $q) use ($branch) {
                $q->whereNull('branch_id');
                if ($branch !== null) {
                    $q->orWhere('branch_id', $branch->id);
                }
            })
            ->orderByRaw('branch_id is null desc')
            ->first();

        return $row;
    }

    private function logMovement(
        Ingredient $ingredient,
        float $delta,
        string $reason,
        ?PosOrder $order,
        ?int $userId,
        ?Branch $branch = null,
    ): void {
        if (! IngredientInventorySchema::movementsReady()) {
            return;
        }

        $branchId = $branch?->id ?? $order?->branch_id ?? $ingredient->branch_id;
        if ($branchId === null) {
            $branchId = Branch::query()
                
                ->orderBy('id')
                ->value('id');
        }

        $payload = [
            'branch_id' => $branchId,
            'ingredient_id' => $ingredient->id,
            'type' => $reason,
            'quantity_delta' => $delta,
            'reference_type' => $order !== null ? PosOrder::class : null,
            'reference_id' => $order?->id,
        ];

        if (IngredientInventorySchema::hasReasonColumn()) {
            $payload['reason'] = $reason;
        }

        if (Schema::hasColumn('inventory_movements', 'user_id')) {
            $payload['user_id'] = $userId;
        }

        if (Schema::hasColumn('inventory_movements', 'pos_order_id')) {
            $payload['pos_order_id'] = $order?->id;
        }

        InventoryMovement::query()->create($payload);
    }
}
