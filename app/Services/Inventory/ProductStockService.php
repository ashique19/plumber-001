<?php

namespace App\Services\Inventory;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Support\Inventory\ProductStockSchema;
use Illuminate\Support\Facades\DB;

class ProductStockService
{
    public function schemaReady(): bool
    {
        return ProductStockSchema::ready();
    }

    public function recordPurchaseLine(Purchase $purchase, PurchaseLine $line, ?int $userId = null): void
    {
        if (! $this->schemaReady()) {
            return;
        }

        DB::transaction(function () use ($purchase, $line, $userId): void {
            $product = Product::query()->lockForUpdate()->find($line->product_id);
            if ($product === null || ! $product->track_stock) {
                return;
            }

            $qty = (float) $line->quantity;
            if ($qty <= 0) {
                return;
            }

            $unitCost = (float) $line->unit_cost_ex_vat;
            $this->applyWeightedAverageCost($product, $qty, $unitCost);
            $product->stock_quantity = (float) $product->stock_quantity + $qty;
            $product->save();

            ProductStockMovement::query()->create([
                'branch_id' => $purchase->branch_id,
                'product_id' => $product->id,
                'type' => 'purchase',
                'quantity_delta' => $qty,
                'unit_cost_ex_vat' => $unitCost,
                'purchase_id' => $purchase->id,
                'user_id' => $userId,
            ]);
        });
    }

    public function syncFromIssuedInvoice(Invoice $invoice, ?int $userId = null): void
    {
        if (! $this->schemaReady() || ! $invoice->isIssued()) {
            return;
        }

        if (ProductStockMovement::query()->where('invoice_id', $invoice->id)->exists()) {
            return;
        }

        $multiplier = match ($invoice->document_type) {
            InvoiceDocumentType::CreditNote => 1.0,
            InvoiceDocumentType::Simplified, InvoiceDocumentType::Standard, InvoiceDocumentType::DebitNote => -1.0,
            default => 0.0,
        };

        if ($multiplier === 0.0) {
            return;
        }

        $lines = $this->invoiceLinesWithProducts($invoice);
        if ($lines === []) {
            return;
        }

        DB::transaction(function () use ($invoice, $lines, $multiplier, $userId): void {
            $type = match ($invoice->document_type) {
                InvoiceDocumentType::CreditNote => 'credit_note',
                default => 'invoice_issue',
            };

            foreach ($lines as $row) {
                $productId = (int) $row['product_id'];
                $qty = (float) $row['quantity'];
                if ($qty <= 0) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($productId);
                if ($product === null || ! $product->track_stock) {
                    continue;
                }

                $delta = round($qty * $multiplier, 3);
                $product->stock_quantity = max(0, (float) $product->stock_quantity + $delta);
                $product->save();

                ProductStockMovement::query()->create([
                    'branch_id' => $invoice->branch_id,
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity_delta' => $delta,
                    'invoice_id' => $invoice->id,
                    'user_id' => $userId,
                ]);
            }
        });
    }

    /**
     * @return list<array{product_id: int, quantity: float}>
     */
    public function invoiceLinesWithProducts(Invoice $invoice): array
    {
        $raw = $invoice->payload['lines'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $productId = $line['product_id'] ?? null;
            if ($productId === null || (int) $productId <= 0) {
                continue;
            }
            $out[] = [
                'product_id' => (int) $productId,
                'quantity' => (float) ($line['quantity'] ?? 0),
            ];
        }

        return $out;
    }

    public function recordOpeningStock(Product $product, float $quantity, float $unitCostExVat, ?int $branchId = null, ?int $userId = null): void
    {
        if (! $this->schemaReady() || ! $product->track_stock || $quantity <= 0) {
            return;
        }

        if (ProductStockMovement::query()->where('product_id', $product->id)->where('type', 'opening')->exists()) {
            return;
        }

        DB::transaction(function () use ($product, $quantity, $unitCostExVat, $branchId, $userId): void {
            $locked = Product::query()->lockForUpdate()->find($product->id);
            if ($locked === null) {
                return;
            }

            $locked->stock_quantity = round((float) $locked->stock_quantity + $quantity, 3);
            if ($unitCostExVat > 0) {
                $locked->cost_ex_vat = round($unitCostExVat, 2);
            }
            $locked->save();

            ProductStockMovement::query()->create([
                'branch_id' => $branchId,
                'product_id' => $locked->id,
                'type' => 'opening',
                'quantity_delta' => round($quantity, 3),
                'unit_cost_ex_vat' => round($unitCostExVat, 2),
                'user_id' => $userId,
                'reason' => 'opening',
            ]);
        });
    }

    public function isLowStock(Product $product): bool
    {
        if (! $product->track_stock) {
            return false;
        }

        return (float) $product->stock_quantity <= (float) $product->low_stock_threshold;
    }

    private function applyWeightedAverageCost(Product $product, float $incomingQty, float $unitCost): void
    {
        $onHand = (float) $product->stock_quantity;
        $currentCost = (float) $product->cost_ex_vat;
        if ($onHand + $incomingQty <= 0) {
            $product->cost_ex_vat = round($unitCost, 2);

            return;
        }

        $weighted = (($onHand * $currentCost) + ($incomingQty * $unitCost)) / ($onHand + $incomingQty);
        $product->cost_ex_vat = round($weighted, 2);
    }
}
