<?php

namespace App\Services\Pos;

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\PosOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PosBranchInsights
{
    /**
     * @return array{
     *   order_count: int,
     *   gross_inc_vat: float,
     *   vat_total: float,
     *   hourly: list<array{hour: int, total: float}>,
     *   top_products: list<array{name: string, qty: int, total: float}>,
     *   recent: Collection<int, PosOrder>
     * }
     */
    public function todaySnapshot(Branch $branch): array
    {
        $start = Carbon::today(config('app.timezone'));
        $end = $start->copy()->endOfDay();

        $orders = PosOrder::query()
            ->where('branch_id', $branch->id)
            ->where('status', PosOrderStatus::Paid)
            ->whereBetween('paid_at', [$start, $end])
            ->orderByDesc('paid_at')
            ->get();

        $hourly = array_fill(0, 24, 0.0);
        $productTotals = [];

        foreach ($orders as $order) {
            $hour = $order->paid_at?->timezone(config('app.timezone'))->hour ?? 0;
            $hourly[$hour] += (float) $order->total_inc_vat;

            foreach ($order->line_items as $line) {
                $name = (string) ($line['name'] ?? 'Item');
                $qty = (int) ($line['quantity'] ?? 1);
                $productTotals[$name] ??= ['name' => $name, 'qty' => 0, 'total' => 0.0];
                $productTotals[$name]['qty'] += $qty;
                $productTotals[$name]['total'] += ((float) ($line['unit_price_ex_vat'] ?? 0) * $qty) * 1.15;
            }
        }

        $hourlyRows = [];
        foreach ($hourly as $hour => $total) {
            if ($total > 0) {
                $hourlyRows[] = ['hour' => $hour, 'total' => round($total, 2)];
            }
        }

        usort($productTotals, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'order_count' => $orders->count(),
            'gross_inc_vat' => round($orders->sum('total_inc_vat'), 2),
            'vat_total' => round($orders->sum('vat_total'), 2),
            'hourly' => $hourlyRows,
            'top_products' => array_slice(array_values($productTotals), 0, 5),
            'recent' => $orders->take(8),
        ];
    }
}
