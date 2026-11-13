<?php

namespace App\Console\Commands;

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ReportsSalesCsvCommand extends Command
{
    protected $signature = 'reports:sales-csv {branch_id} {--date=}';

    protected $description = 'Export paid POS orders for a branch/date to storage CSV.';

    public function handle(): int
    {
        $branch = Branch::query()->withoutGlobalScopes()->findOrFail((int) $this->argument('branch_id'));
        $date = $this->option('date') ?? today()->toDateString();

        $orders = $branch->posOrders()
            ->withoutGlobalScopes()
            ->where('status', PosOrderStatus::Paid)
            ->whereDate('paid_at', $date)
            ->orderBy('id')
            ->get();

        $lines = ["order_number,payment_method,subtotal_ex_vat,vat_total,total_inc_vat,paid_at"];
        foreach ($orders as $order) {
            $lines[] = implode(',', [
                $order->order_number,
                $order->payment_method,
                $order->subtotal_ex_vat,
                $order->vat_total,
                $order->total_inc_vat,
                $order->paid_at?->toIso8601String(),
            ]);
        }

        $path = "reports/sales-{$branch->id}-{$date}.csv";
        Storage::disk('local')->put($path, implode("\n", $lines));
        $this->info(storage_path('app/'.$path));

        return self::SUCCESS;
    }
}
