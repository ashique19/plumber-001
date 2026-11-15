<?php

namespace App\Services\Business;

use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Ingredient;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Services\Inventory\IngredientStockService;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Inventory\IngredientInventorySchema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class AdminExtendedReportsService
{
    public function __construct(
        private readonly IngredientStockService $stock,
    ) {}

    /**
     * @param  Collection<int, PosOrder>  $paidOrders  paid POS orders in the selected window
     * @return array<string, mixed>
     */
    public function build(
        BusinessSetting $business,
        ?Branch $branch,
        Carbon $from,
        Carbon $to,
        Collection $paidOrders,
    ): array {
        return [
            'vat_summary' => $this->vatSummary($paidOrders),
            'profit' => $this->profitSummary($paidOrders, $branch),
            'inventory' => $this->inventorySnapshot($branch),
            'dues_aging' => $this->duesAging($business, $branch, $from, $to),
        ];
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return array<string, mixed>
     */
    private function vatSummary(Collection $orders): array
    {
        $exVat = round((float) $orders->sum('subtotal_ex_vat') - (float) $orders->sum('discount_ex_vat')
            - (float) $orders->sum('promo_discount_ex_vat') - (float) $orders->sum('loyalty_discount_ex_vat'), 2);
        $vat = round((float) $orders->sum('vat_total'), 2);
        $incVat = round((float) $orders->sum('total_inc_vat'), 2);

        return [
            'taxable_ex_vat' => max(0, $exVat),
            'vat_collected' => $vat,
            'gross_inc_vat' => $incVat,
            'order_count' => $orders->count(),
        ];
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return array<string, mixed>
     */
    private function profitSummary(Collection $orders, ?Branch $branch): array
    {
        $salesExVat = round((float) $orders->sum('subtotal_ex_vat') - (float) $orders->sum('discount_ex_vat')
            - (float) $orders->sum('promo_discount_ex_vat') - (float) $orders->sum('loyalty_discount_ex_vat'), 2);
        $salesExVat = max(0, $salesExVat);

        if (! IngredientInventorySchema::hasUnitCost()) {
            return [
                'sales_ex_vat' => $salesExVat,
                'cogs_ex_vat' => null,
                'gross_profit_ex_vat' => null,
                'margin_pct' => null,
                'cost_status' => 'unknown',
            ];
        }

        $cogs = 0.0;
        $missingCost = false;

        foreach ($orders as $order) {
            foreach ($order->line_items as $line) {
                $productId = $line['product_id'] ?? null;
                if ($productId === null) {
                    $missingCost = true;

                    continue;
                }

                $qty = (float) ($line['quantity'] ?? 1);
                $bom = $this->stock->billOfMaterials((int) $productId);
                if ($bom->isEmpty()) {
                    $missingCost = true;

                    continue;
                }

                foreach ($bom as $bomLine) {
                    $ingredient = $this->resolveIngredient($branch, (int) $bomLine['ingredient_id']);
                    if ($ingredient === null || $ingredient->unitCostExVat() === null) {
                        $missingCost = true;

                        continue;
                    }

                    $cogs += (float) $bomLine['quantity_per_unit'] * $qty * (float) $ingredient->unitCostExVat();
                }
            }
        }

        $cogs = round($cogs, 2);

        if ($cogs <= 0 && $missingCost) {
            return [
                'sales_ex_vat' => $salesExVat,
                'cogs_ex_vat' => null,
                'gross_profit_ex_vat' => null,
                'margin_pct' => null,
                'cost_status' => 'unknown',
            ];
        }

        $profit = round($salesExVat - $cogs, 2);
        $margin = $salesExVat > 0 ? round(($profit / $salesExVat) * 100, 1) : 0.0;

        return [
            'sales_ex_vat' => $salesExVat,
            'cogs_ex_vat' => $cogs,
            'gross_profit_ex_vat' => $profit,
            'margin_pct' => $margin,
            'cost_status' => $missingCost ? 'partial' : 'known',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function inventorySnapshot(?Branch $branch): array
    {
        if (! IngredientInventorySchema::ingredientsReady()) {
            return [
                'valuation_status' => 'unavailable',
                'valuation_total_ex_vat' => null,
                'lines' => [],
                'low_stock' => [],
            ];
        }

        $query = Ingredient::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($q) use ($branch) {
                $q->whereNull('branch_id');
                if ($branch !== null) {
                    $q->orWhere('branch_id', $branch->id);
                }
            });

        $ingredients = IngredientInventorySchema::applyDisplayNameOrder($query)->get();
        $hasCost = IngredientInventorySchema::hasUnitCost();
        $valuation = 0.0;
        $lines = [];

        foreach ($ingredients as $ingredient) {
            $qty = (float) $ingredient->quantity_on_hand;
            $unitCost = $hasCost ? $ingredient->unitCostExVat() : null;
            $lineValue = $unitCost !== null ? round($qty * $unitCost, 2) : null;
            if ($lineValue !== null) {
                $valuation += $lineValue;
            }

            $lines[] = [
                'id' => $ingredient->id,
                'name' => $ingredient->displayName(),
                'quantity' => $qty,
                'unit' => (string) $ingredient->unit,
                'unit_cost_ex_vat' => $unitCost,
                'value_ex_vat' => $lineValue,
            ];
        }

        $lowStock = array_map(static fn (Ingredient $ing): array => [
            'id' => $ing->id,
            'name' => $ing->displayName(),
            'quantity' => (float) $ing->quantity_on_hand,
            'unit' => (string) $ing->unit,
            'threshold' => IngredientInventorySchema::hasLowStockThreshold()
                ? (float) ($ing->low_stock_threshold ?? 0)
                : (float) ($ing->reorder_level ?? 0),
        ], $this->stock->lowStockForBusiness($branch, 25));

        return [
            'valuation_status' => $hasCost ? 'known' : 'unknown',
            'valuation_total_ex_vat' => $hasCost ? round($valuation, 2) : null,
            'lines' => $lines,
            'low_stock' => $lowStock,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function duesAging(
        BusinessSetting $business,
        ?Branch $branch,
        Carbon $from,
        Carbon $to,
    ): array {
        $asOf = $to->copy()->timezone(config('app.timezone'))->startOfDay();
        $termsDays = max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30));

        $buckets = [
            'current' => ['count' => 0, 'total_inc_vat' => 0.0],
            'days_1_30' => ['count' => 0, 'total_inc_vat' => 0.0],
            'days_31_60' => ['count' => 0, 'total_inc_vat' => 0.0],
            'days_61_90' => ['count' => 0, 'total_inc_vat' => 0.0],
            'days_90_plus' => ['count' => 0, 'total_inc_vat' => 0.0],
        ];

        $items = [];
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->endOfDay();

        $creditOrders = PosOrder::query()
            ->withoutGlobalScopes()
            ->where('status', PosOrderStatus::Paid)
            ->where('payment_method', 'credit')
            ->whereBetween('paid_at', [$windowStart, $windowEnd])
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->with('customer')
            ->orderBy('paid_at')
            ->get();

        foreach ($creditOrders as $order) {
            $paidAt = $order->paid_at?->timezone(config('app.timezone'));
            if ($paidAt === null) {
                continue;
            }

            $due = $paidAt->copy()->addDays($termsDays)->startOfDay();
            $amount = round((float) $order->total_inc_vat, 2);
            $bucketKey = $this->agingBucket($due, $asOf);
            $buckets[$bucketKey]['count']++;
            $buckets[$bucketKey]['total_inc_vat'] = round($buckets[$bucketKey]['total_inc_vat'] + $amount, 2);

            $items[] = [
                'reference' => $order->order_number,
                'kind' => 'pos_credit',
                'customer' => $order->customer?->name,
                'issued_at' => $paidAt->toDateString(),
                'due_date' => $due->toDateString(),
                'amount_inc_vat' => $amount,
                'bucket' => $bucketKey,
            ];
        }

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('status', InvoiceStatus::Issued)
            ->where('document_type', InvoiceDocumentType::Standard)
            ->where(fn ($q) => $q->whereNull('source_type')->orWhere('source_type', '!=', PosOrder::class))
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$windowStart->utc(), $windowEnd->utc()])
            ->when($branch !== null, fn ($q) => $q->where('branch_id', $branch->id))
            ->orderBy('issued_at')
            ->get();

        foreach ($invoices as $invoice) {
            $payload = $invoice->payload ?? [];
            $payment = strtolower((string) ($payload['payment_method'] ?? 'credit'));
            if (! in_array($payment, ['credit', 'transfer'], true)) {
                continue;
            }

            $amount = $this->invoiceNetDue($invoice);
            if ($amount <= 0) {
                continue;
            }

            $issued = $invoice->issued_at?->timezone(config('app.timezone'));
            $due = $invoice->due_date
                ? $invoice->due_date->copy()->timezone(config('app.timezone'))->startOfDay()
                : ($issued?->copy()->addDays($termsDays)->startOfDay());

            if ($due === null) {
                continue;
            }

            $bucketKey = $this->agingBucket($due, $asOf);
            $buckets[$bucketKey]['count']++;
            $buckets[$bucketKey]['total_inc_vat'] = round($buckets[$bucketKey]['total_inc_vat'] + $amount, 2);

            $items[] = [
                'reference' => $invoice->document_number,
                'kind' => 'invoice',
                'customer' => trim((string) ($payload['buyer_name'] ?? '')) ?: null,
                'issued_at' => $issued?->toDateString(),
                'due_date' => $due->toDateString(),
                'amount_inc_vat' => $amount,
                'bucket' => $bucketKey,
            ];
        }

        $total = round(array_sum(array_column($buckets, 'total_inc_vat')), 2);

        return [
            'as_of' => $asOf->toDateString(),
            'buckets' => $buckets,
            'items' => $items,
            'total_outstanding_inc_vat' => $total,
        ];
    }

    private function agingBucket(Carbon $dueDate, Carbon $asOf): string
    {
        if ($dueDate->greaterThan($asOf)) {
            return 'current';
        }

        $days = (int) $dueDate->diffInDays($asOf);

        return match (true) {
            $days <= 30 => 'days_1_30',
            $days <= 60 => 'days_31_60',
            $days <= 90 => 'days_61_90',
            default => 'days_90_plus',
        };
    }

    private function invoiceNetDue(Invoice $invoice): float
    {
        $payload = $invoice->payload ?? [];
        $raw = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $lines = array_map(static function (array $line): InvoiceLineData {
            return new InvoiceLineData(
                name: (string) ($line['name'] ?? ''),
                quantity: (float) ($line['quantity'] ?? 0),
                unitPriceExVat: (float) ($line['unit_price_ex_vat'] ?? 0),
                vatRate: (float) ($line['vat_rate'] ?? 15),
                discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
            );
        }, $raw);

        $invoiceDiscount = (float) ($payload['invoice_discount_ex_vat'] ?? 0);
        $retentionPercent = isset($payload['retention_percent']) ? (float) $payload['retention_percent'] : null;
        $retentionFixed = isset($payload['retention_fixed']) ? (float) $payload['retention_fixed'] : null;

        $totals = InvoiceDocumentTotals::compute($lines, $invoiceDiscount, $retentionPercent, $retentionFixed);

        return round((float) $totals['net_due_inc_vat'], 2);
    }

    private function resolveIngredient(?Branch $branch, int $ingredientId): ?Ingredient
    {
        return Ingredient::query()
            ->withoutGlobalScopes()
            ->where('id', $ingredientId)
            ->where(function ($q) use ($branch) {
                $q->whereNull('branch_id');
                if ($branch !== null) {
                    $q->orWhere('branch_id', $branch->id);
                }
            })
            ->orderByRaw('branch_id is null desc')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $extended
     */
    public function appendCsv(string $baseCsv, array $extended): string
    {
        $lines = rtrim($baseCsv, "\n")."\n";
        $vat = $extended['vat_summary'];
        foreach ($vat as $key => $value) {
            $lines .= 'vat_summary,'.$key.','.$value."\n";
        }

        $profit = $extended['profit'];
        foreach ($profit as $key => $value) {
            $lines .= 'profit,'.$key.','.($value ?? 'null')."\n";
        }

        $inventory = $extended['inventory'];
        $lines .= 'inventory,valuation_status,'.$inventory['valuation_status']."\n";
        $lines .= 'inventory,valuation_total_ex_vat,'.($inventory['valuation_total_ex_vat'] ?? 'null')."\n";
        foreach ($inventory['low_stock'] as $row) {
            $lines .= 'low_stock,'.$this->csvEscape($row['name']).','.$row['quantity'].','.$row['threshold']."\n";
        }

        $dues = $extended['dues_aging'];
        $lines .= 'dues,total_outstanding_inc_vat,'.$dues['total_outstanding_inc_vat']."\n";
        foreach ($dues['buckets'] as $bucket => $row) {
            $lines .= 'dues_bucket,'.$bucket.','.$row['total_inc_vat'].','.$row['count']."\n";
        }

        return $lines;
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}