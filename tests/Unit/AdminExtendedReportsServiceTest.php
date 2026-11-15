<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Services\Business\AdminExtendedReportsService;
use App\Services\Pos\PosAnalyticsService;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('marks profit unknown when ingredient unit cost column is absent', function () {
    $from = Carbon::now()->subDays(7)->startOfDay();
    $to = Carbon::now()->endOfDay();

    $order = PosOrder::query()->create([
        'branch_id' => $this->branch->id,
        'order_number' => 'EXT-PROFIT-1',
        'status' => PosOrderStatus::Paid,
        'payment_method' => 'cash',
        'subtotal_ex_vat' => 100,
        'vat_total' => 15,
        'total_inc_vat' => 115,
        'line_items' => [],
        'paid_at' => now(),
    ]);

    $service = app(AdminExtendedReportsService::class);
    $profit = $service->build($this->business, $this->branch, $from, $to, collect([$order]))['profit'];

    expect($profit['cost_status'])->toBe('unknown')
        ->and($profit['gross_profit_ex_vat'])->toBeNull()
        ->and($profit['sales_ex_vat'])->toBe(100.0);
});

it('aggregates credit POS sales into dues aging buckets', function () {
    $paidAt = Carbon::parse('2026-11-01 12:00:00', 'Asia/Riyadh');
    $from = $paidAt->copy()->startOfMonth();
    $to = $paidAt->copy()->endOfMonth();

    PosOrder::query()->create([
        'branch_id' => $this->branch->id,
        'order_number' => 'CREDIT-AGING-1',
        'status' => PosOrderStatus::Paid,
        'payment_method' => 'credit',
        'subtotal_ex_vat' => 200,
        'vat_total' => 30,
        'total_inc_vat' => 230,
        'line_items' => [],
        'paid_at' => $paidAt,
    ]);

    $service = app(AdminExtendedReportsService::class);
    $dues = $service->build($this->business, $this->branch, $from, $to, collect())['dues_aging'];

    expect($dues['total_outstanding_inc_vat'])->toBe(230.0)
        ->and($dues['items'])->toHaveCount(1)
        ->and($dues['buckets']['current']['count'] + $dues['buckets']['days_1_30']['count'])->toBeGreaterThanOrEqual(1);
});

it('includes extended sections in analytics csv export', function () {
    $from = Carbon::now()->subDays(2)->startOfDay();
    $to = Carbon::now()->endOfDay();
    /** @var PosAnalyticsService $analytics */
    $analytics = app(PosAnalyticsService::class);
    $report = $analytics->report($this->business, $this->branch, $from, $to, 'day');
    $csv = $analytics->toCsv($report);

    expect($csv)->toContain('vat_summary,')
        ->and($csv)->toContain('profit,cost_status')
        ->and($csv)->toContain('dues,total_outstanding_inc_vat');
});

it('computes profit when unit cost column exists', function () {
    if (! Schema::hasTable('ingredients')) {
        $this->markTestSkipped('ingredients table missing');
    }

    Schema::table('ingredients', function ($table) {
        if (! Schema::hasColumn('ingredients', 'unit_cost_ex_vat')) {
            $table->decimal('unit_cost_ex_vat', 12, 4)->nullable()->after('quantity_on_hand');
        }
    });

    $ingredient = \App\Models\Ingredient::query()->create([
        'name_en' => 'Copper pipe',
        'unit' => 'm',
        'quantity_on_hand' => 50,
        'unit_cost_ex_vat' => 10,
        'low_stock_threshold' => 0,
        'is_active' => true,
    ]);

    $product = \App\Models\Product::query()->firstOrFail();
    \App\Models\ProductIngredient::query()->create([
        'product_id' => $product->id,
        'ingredient_id' => $ingredient->id,
        'quantity_per_unit' => 1,
    ]);

    $from = Carbon::now()->subDay()->startOfDay();
    $to = Carbon::now()->endOfDay();
    $order = PosOrder::query()->create([
        'branch_id' => $this->branch->id,
        'order_number' => 'EXT-PROFIT-2',
        'status' => PosOrderStatus::Paid,
        'payment_method' => 'cash',
        'subtotal_ex_vat' => 100,
        'vat_total' => 15,
        'total_inc_vat' => 115,
        'line_items' => [
            [
                'product_id' => $product->id,
                'name' => $product->name_en,
                'quantity' => 2,
                'unit_price_ex_vat' => 50,
                'vat_rate' => 15,
            ],
        ],
        'paid_at' => now(),
    ]);

    $service = app(AdminExtendedReportsService::class);
    $profit = $service->build($this->business, $this->branch, $from, $to, collect([$order]))['profit'];

    expect($profit['cost_status'])->toBe('known')
        ->and($profit['cogs_ex_vat'])->toBe(20.0)
        ->and($profit['gross_profit_ex_vat'])->toBe(80.0);
});

it('includes standard invoice credit in dues aging', function () {
    $issuedAt = Carbon::parse('2026-11-05 10:00:00', 'Asia/Riyadh');
    $from = $issuedAt->copy()->startOfMonth();
    $to = $issuedAt->copy()->endOfMonth();

    Invoice::query()->create([
        'branch_id' => $this->branch->id,
        'document_number' => 'STD-DUE-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt->utc(),
        'due_date' => $issuedAt->copy()->addDays(15)->toDateString(),
        'payload' => [
            'payment_method' => 'credit',
            'buyer_name' => 'Buyer Co',
            'lines' => [
                [
                    'name' => 'Service',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 500,
                    'vat_rate' => 15,
                ],
            ],
        ],
    ]);

    $service = app(AdminExtendedReportsService::class);
    $dues = $service->build($this->business, $this->branch, $from, $to, collect())['dues_aging'];

    expect($dues['total_outstanding_inc_vat'])->toBe(575.0)
        ->and(collect($dues['items'])->pluck('reference'))->toContain('STD-DUE-1');
});