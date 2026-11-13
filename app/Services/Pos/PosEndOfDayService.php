<?php

namespace App\Services\Pos;

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PosEndOfDayService
{
    /**
     * @return array{
     *     branch_id: int,
     *     date: string,
     *     order_count: int,
     *     gross_inc_vat: float,
     *     vat_total: float,
     *     by_payment: array<string, array{count: int, total_inc_vat: float}>
     * }
     */
    public function summarize(Branch $branch, ?Carbon $date = null): array
    {
        $date ??= today();
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        $rows = DB::table('pos_orders')
            ->where('branch_id', $branch->id)
            ->where('status', PosOrderStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end])
            ->selectRaw('payment_method, COUNT(*) as cnt, SUM(total_inc_vat) as total')
            ->groupBy('payment_method')
            ->get();

        $byPayment = [];
        $orderCount = 0;
        $gross = 0.0;

        foreach ($rows as $row) {
            $method = (string) ($row->payment_method ?? 'unknown');
            $byPayment[$method] = [
                'count' => (int) $row->cnt,
                'total_inc_vat' => round((float) $row->total, 2),
            ];
            $orderCount += (int) $row->cnt;
            $gross += (float) $row->total;
        }

        $vat = (float) DB::table('pos_orders')
            ->where('branch_id', $branch->id)
            ->where('status', PosOrderStatus::Paid->value)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('vat_total');

        return [
            'branch_id' => $branch->id,
            'date' => $date->toDateString(),
            'order_count' => $orderCount,
            'gross_inc_vat' => round($gross, 2),
            'vat_total' => round($vat, 2),
            'by_payment' => $byPayment,
        ];
    }
}
