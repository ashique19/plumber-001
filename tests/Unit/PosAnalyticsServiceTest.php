<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\PosOrderStatus;
use App\Enums\PosPaymentMethod;
use App\Models\PosOrder;
use App\Services\Pos\PosAnalyticsService;
use Illuminate\Support\Carbon;

it('aggregates paid orders and round-trips csv sections', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();

    $paidAt = Carbon::now()->startOfHour();
    PosOrder::query()->create([
        'tenant_id' => $tenant->id,
        'branch_id' => $branch->id,
        'cashier_id' => $cashier->id,
        'order_number' => 'T-100',
        'status' => PosOrderStatus::Paid,
        'payment_method' => PosPaymentMethod::Cash->value,
        'subtotal_ex_vat' => 20,
        'discount_ex_vat' => 0,
        'promo_discount_ex_vat' => 0,
        'loyalty_discount_ex_vat' => 0,
        'vat_total' => 3,
        'total_inc_vat' => 23,
        'line_items' => [[
            'product_id' => $product->id,
            'name' => 'Latte',
            'quantity' => 1,
            'unit_price_ex_vat' => 20,
            'vat_rate' => 15,
        ]],
        'paid_at' => $paidAt,
    ]);

    $svc = app(PosAnalyticsService::class);
    $from = $paidAt->copy()->startOfDay();
    $to = $paidAt->copy()->endOfDay();
    $report = $svc->report($tenant, $branch, $from, $to, 'day');

    expect($report['summary']['order_count'])->toBe(1)
        ->and($report['summary']['gross_inc_vat'])->toBe(23.0)
        ->and($report['summary']['vat_total'])->toBe(3.0)
        ->and($report['top_products'][0]['name'])->toBe('Latte');

    $csv = $svc->toCsv($report);
    expect($csv)->toContain('summary,order_count,1')
        ->and($csv)->toContain('sales_series');
});
