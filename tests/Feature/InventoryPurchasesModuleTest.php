<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Livewire\Purchases\CreatePurchase;
use App\Livewire\Stock\StockSearch;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductStockMovement;
use App\Models\StaffUser;
use App\Services\InvoiceIssueService;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->firstOrFail();
    config(['zheart.base_url' => '', 'zheart.api_key' => '']);
});

it('registers stock search and purchase create routes', function () {
    expect(route('admin.stock.search', absolute: false))->toBe('/admin/stock/search')
        ->and(route('admin.purchases.create', absolute: false))->toBe('/admin/purchases/create');
});

it('records a purchase and increases tracked stock with cost', function () {
    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 2,
        'cost_ex_vat' => 10,
        'low_stock_threshold' => 5,
        'price_ex_vat' => 50,
        'vat_rate' => 15,
        'name_en' => 'Copper elbow',
        'name_ar' => 'كوع نحاس',
    ]);

    $supplier = \App\Models\Supplier::query()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Al Rajhi Supplies',
        'vat_number' => '300000000000003',
        'phone' => '0500000001',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->set('supplierId', $supplier->id)
        ->set('lines', [
            ['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '20', 'vat_rate' => '15'],
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    $product->refresh();
    expect((float) $product->stock_quantity)->toBe(12.0)
        ->and((float) $product->cost_ex_vat)->toBe(18.33);

    expect(ProductStockMovement::query()->where('product_id', $product->id)->where('type', 'purchase')->count())->toBe(1);
});

it('decreases stock on invoice issue and restores on credit note', function () {
    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 10,
        'low_stock_threshold' => 2,
        'price_ex_vat' => 100,
        'vat_rate' => 15,
        'name_en' => 'Valve kit',
        'name_ar' => 'طقم صمام',
    ]);

    $issuer = app(InvoiceIssueService::class);
    $draft = $issuer->issueDraft(
        $this->branch,
        'SIMP-STOCK-1',
        InvoiceDocumentType::Simplified,
        [
            [
                'name' => 'Valve kit',
                'quantity' => 3,
                'unit_price_ex_vat' => 100,
                'vat_rate' => 15,
                'product_id' => $product->id,
            ],
        ],
    );

    $issuer->issue($draft);
    $product->refresh();
    expect((float) $product->stock_quantity)->toBe(7.0);

    $issued = $draft->fresh();
    $creditDraft = $issuer->issueDraft(
        $this->branch,
        'CN-STOCK-1',
        InvoiceDocumentType::CreditNote,
        [
            [
                'name' => 'Valve kit',
                'quantity' => 1,
                'unit_price_ex_vat' => 100,
                'vat_rate' => 15,
                'product_id' => $product->id,
            ],
        ],
        $issued,
    );
    $issuer->issue($creditDraft);
    $product->refresh();
    expect((float) $product->stock_quantity)->toBe(8.0);
});

it('filters low stock on stock search', function () {
    Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 1,
        'low_stock_threshold' => 5,
        'price_ex_vat' => 10,
        'vat_rate' => 15,
        'name_en' => 'Low item',
        'name_ar' => 'منخفض',
    ]);
    Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 50,
        'low_stock_threshold' => 5,
        'price_ex_vat' => 10,
        'vat_rate' => 15,
        'name_en' => 'Healthy item',
        'name_ar' => 'جيد',
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(StockSearch::class)
        ->set('lowStockOnly', true)
        ->assertSee('Low item', false)
        ->assertDontSee('Healthy item', false);
});
