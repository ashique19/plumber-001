<?php

namespace App\Services\Pos;

use App\Enums\PosOrderStatus;
use App\Enums\PosPaymentMethod;
use App\Models\Branch;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PosAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function report(
        BusinessSetting $tenant,
        ?Branch $branch,
        Carbon $from,
        Carbon $to,
        string $granularity = 'day',
    ): array {
        $orders = $this->paidOrders($tenant, $branch, $from, $to);

        $count = $orders->count();
        $gross = round((float) $orders->sum('total_inc_vat'), 2);
        $vat = round((float) $orders->sum('vat_total'), 2);
        $aov = $count > 0 ? round($gross / $count, 2) : 0.0;

        $prevSpan = $to->diffInSeconds($from);
        $prevFrom = $from->copy()->subSeconds($prevSpan + 1);
        $prevTo = $from->copy()->subSecond();
        $prev = $this->paidOrders($tenant, $branch, $prevFrom, $prevTo);
        $prevGross = (float) $prev->sum('total_inc_vat');
        $trendPct = $prevGross > 0
            ? round((($gross - $prevGross) / $prevGross) * 100, 1)
            : ($gross > 0 ? 100.0 : 0.0);

        return [
            'summary' => [
                'order_count' => $count,
                'gross_inc_vat' => $gross,
                'vat_total' => $vat,
                'avg_order_value' => $aov,
                'trend_gross_pct' => $trendPct,
            ],
            'sales_series' => $this->salesSeries($orders, $from, $to, $granularity),
            'hourly_heatmap' => $this->hourlyHeatmap($orders),
            'top_products' => $this->topProducts($orders, 10),
            'category_mix' => $this->categoryMix($tenant, $orders),
            'payment_split' => $this->paymentSplit($orders),
            'branch_comparison' => $branch === null ? $this->branchComparison($tenant, $orders) : [],
            'cashier_performance' => $this->cashierPerformance($tenant, $orders),
            'meta' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'granularity' => $granularity,
                'branch_id' => $branch?->id,
            ],
        ];
    }

    /**
     * @return Collection<int, PosOrder>
     */
    private function paidOrders(BusinessSetting $tenant, ?Branch $branch, Carbon $from, Carbon $to): Collection
    {
        $q = PosOrder::query()
            ->withoutGlobalScopes()
            ->where('status', PosOrderStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->orderBy('paid_at');

        if ($branch !== null) {
            $q->where('branch_id', $branch->id);
        }

        return $q->get();
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{label: string, total: float, orders: int}>
     */
    private function salesSeries(Collection $orders, Carbon $from, Carbon $to, string $granularity): array
    {
        $buckets = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        while ($cursor <= $end) {
            $key = $this->bucketKey($cursor, $granularity);
            $buckets[$key] ??= ['label' => $this->bucketLabel($cursor, $granularity), 'total' => 0.0, 'orders' => 0];
            $cursor = $this->advanceBucket($cursor, $granularity);
        }

        foreach ($orders as $order) {
            $paid = $order->paid_at?->timezone(config('app.timezone')) ?? $from;
            $key = $this->bucketKey($paid, $granularity);
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['label' => $this->bucketLabel($paid, $granularity), 'total' => 0.0, 'orders' => 0];
            }
            $buckets[$key]['total'] += (float) $order->total_inc_vat;
            $buckets[$key]['orders'] += 1;
        }

        foreach ($buckets as &$row) {
            $row['total'] = round($row['total'], 2);
        }

        return array_values($buckets);
    }

    private function bucketKey(Carbon $dt, string $granularity): string
    {
        return match ($granularity) {
            'week' => $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'month' => $dt->format('Y-m'),
            default => $dt->format('Y-m-d'),
        };
    }

    private function bucketLabel(Carbon $dt, string $granularity): string
    {
        return match ($granularity) {
            'week' => $dt->copy()->startOfWeek(Carbon::MONDAY)->format('d M'),
            'month' => $dt->format('M Y'),
            default => $dt->format('d M'),
        };
    }

    private function advanceBucket(Carbon $dt, string $granularity): Carbon
    {
        return match ($granularity) {
            'week' => $dt->addWeek(),
            'month' => $dt->addMonth(),
            default => $dt->addDay(),
        };
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{dow: int, hour: int, total: float}>
     */
    private function hourlyHeatmap(Collection $orders): array
    {
        $grid = [];
        foreach ($orders as $order) {
            if ($order->paid_at === null) {
                continue;
            }
            $paid = $order->paid_at->timezone(config('app.timezone'));
            $dow = $paid->dayOfWeekIso - 1;
            $hour = $paid->hour;
            $key = "{$dow}-{$hour}";
            $grid[$key] ??= ['dow' => $dow, 'hour' => $hour, 'total' => 0.0];
            $grid[$key]['total'] += (float) $order->total_inc_vat;
        }

        $rows = array_values($grid);
        foreach ($rows as &$row) {
            $row['total'] = round($row['total'], 2);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{name: string, qty: int, total: float}>
     */
    private function topProducts(Collection $orders, int $limit): array
    {
        $totals = [];
        foreach ($orders as $order) {
            foreach ($order->line_items as $line) {
                $name = (string) ($line['name'] ?? 'Item');
                $qty = (int) ($line['quantity'] ?? 1);
                $unit = (float) ($line['unit_price_ex_vat'] ?? 0);
                $rate = (float) ($line['vat_rate'] ?? 15);
                $lineGross = $unit * $qty * (1 + $rate / 100);
                $totals[$name] ??= ['name' => $name, 'qty' => 0, 'total' => 0.0];
                $totals[$name]['qty'] += $qty;
                $totals[$name]['total'] += $lineGross;
            }
        }

        usort($totals, fn ($a, $b) => $b['total'] <=> $a['total']);
        $slice = array_slice(array_values($totals), 0, $limit);
        foreach ($slice as &$row) {
            $row['total'] = round($row['total'], 2);
        }

        return $slice;
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{label: string, total: float, pct: float}>
     */
    private function categoryMix(BusinessSetting $tenant, Collection $orders): array
    {
        $productIds = [];
        foreach ($orders as $order) {
            foreach ($order->line_items as $line) {
                $id = $line['product_id'] ?? null;
                if ($id) {
                    $productIds[$id] = true;
                }
            }
        }

        $products = Product::query()
            ->withoutGlobalScopes()
            ->with('category')
            ->whereIn('id', array_keys($productIds))
            ->get()
            ->keyBy('id');

        $byCat = [];
        $grand = 0.0;

        foreach ($orders as $order) {
            foreach ($order->line_items as $line) {
                $pid = $line['product_id'] ?? null;
                $catLabel = 'Uncategorized';
                if ($pid && $products->has($pid)) {
                    $cat = $products->get($pid)->category;
                    $catLabel = $cat?->name_en ?? $catLabel;
                }
                $qty = (int) ($line['quantity'] ?? 1);
                $unit = (float) ($line['unit_price_ex_vat'] ?? 0);
                $rate = (float) ($line['vat_rate'] ?? 15);
                $gross = $unit * $qty * (1 + $rate / 100);
                $byCat[$catLabel] ??= 0.0;
                $byCat[$catLabel] += $gross;
                $grand += $gross;
            }
        }

        $rows = [];
        foreach ($byCat as $label => $total) {
            $rows[] = [
                'label' => $label,
                'total' => round($total, 2),
                'pct' => $grand > 0 ? round(($total / $grand) * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{method: string, label: string, total: float, orders: int, pct: float}>
     */
    private function paymentSplit(Collection $orders): array
    {
        $by = [];
        $grand = (float) $orders->sum('total_inc_vat');

        foreach ($orders as $order) {
            $method = $order->payment_method ?? PosPaymentMethod::Cash;
            $key = $method instanceof PosPaymentMethod ? $method->value : (string) $method;
            $by[$key] ??= ['method' => $key, 'total' => 0.0, 'orders' => 0];
            $by[$key]['total'] += (float) $order->total_inc_vat;
            $by[$key]['orders'] += 1;
        }

        $rows = [];
        foreach ($by as $row) {
            $rows[] = [
                'method' => $row['method'],
                'label' => $row['method'],
                'total' => round($row['total'], 2),
                'orders' => $row['orders'],
                'pct' => $grand > 0 ? round(($row['total'] / $grand) * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{branch_id: int, name: string, total: float, orders: int}>
     */
    private function branchComparison(BusinessSetting $tenant, Collection $orders): array
    {
        $branches = Branch::query()
            ->withoutGlobalScopes()
            ->get()
            ->keyBy('id');

        $by = [];
        foreach ($orders as $order) {
            $bid = (int) $order->branch_id;
            $by[$bid] ??= ['branch_id' => $bid, 'name' => $branches->get($bid)?->name ?? "#{$bid}", 'total' => 0.0, 'orders' => 0];
            $by[$bid]['total'] += (float) $order->total_inc_vat;
            $by[$bid]['orders'] += 1;
        }

        $rows = array_values($by);
        foreach ($rows as &$row) {
            $row['total'] = round($row['total'], 2);
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{cashier_id: int|null, name: string, total: float, orders: int, aov: float}>
     */
    private function cashierPerformance(BusinessSetting $tenant, Collection $orders): array
    {
        $userIds = $orders->pluck('cashier_id')->filter()->unique()->values();
        $users = StaffUser::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $by = [];
        foreach ($orders as $order) {
            $cid = $order->cashier_id;
            $key = $cid ?? 0;
            $by[$key] ??= [
                'cashier_id' => $cid,
                'name' => $cid && $users->has($cid) ? $users->get($cid)->name : __('reports.unknown_cashier'),
                'total' => 0.0,
                'orders' => 0,
            ];
            $by[$key]['total'] += (float) $order->total_inc_vat;
            $by[$key]['orders'] += 1;
        }

        $rows = array_values($by);
        foreach ($rows as &$row) {
            $row['total'] = round($row['total'], 2);
            $row['aov'] = $row['orders'] > 0 ? round($row['total'] / $row['orders'], 2) : 0.0;
        }
        usort($rows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    public function toCsv(array $report): string
    {
        $lines = [];
        $lines[] = 'section,metric,value';
        $summary = $report['summary'];
        foreach ($summary as $key => $value) {
            $lines[] = 'summary,'.$key.','.$value;
        }

        $lines[] = 'series,label,total_sar,orders';
        foreach ($report['sales_series'] as $row) {
            $lines[] = 'sales_series,'.$this->csvEscape($row['label']).','.$row['total'].','.$row['orders'];
        }

        foreach ($report['top_products'] as $row) {
            $lines[] = 'top_product,'.$this->csvEscape($row['name']).','.$row['total'].','.$row['qty'];
        }

        foreach ($report['payment_split'] as $row) {
            $lines[] = 'payment,'.$row['method'].','.$row['total'].','.$row['orders'];
        }

        return implode("\n", $lines)."\n";
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
