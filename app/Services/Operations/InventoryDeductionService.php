<?php

namespace App\Services\Operations;

use App\Models\PosOrder;
use App\Services\Inventory\IngredientStockService;

class InventoryDeductionService
{
    public function __construct(
        private readonly IngredientStockService $stock,
    ) {}

    public function deductForPaidOrder(PosOrder $order, ?int $userId = null): void
    {
        $this->stock->deductForPaidOrder($order, $userId);
    }

    public function restoreForRefundedOrder(PosOrder $order, ?int $userId = null): void
    {
        $this->stock->restoreForRefundedOrder($order, $userId);
    }
}
