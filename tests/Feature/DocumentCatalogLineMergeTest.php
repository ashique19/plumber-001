<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Purchases\CreatePurchase;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StaffUser;
use App\Support\Inventory\ProductStockSchema;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('merges duplicate catalog picks into one invoice line with increased quantity', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified']);

    $component->call('addLineFromProduct', $this->product->id);
    $component->call('addLineFromProduct', $this->product->id);
    $component->call('addLineFromProduct', $this->product->id);

    $filled = collect($component->get('lines'))->filter(fn ($row) => filled($row['product_id'] ?? null));

    expect($filled)->toHaveCount(1)
        ->and($filled->first()['quantity'])->toBe('3')
        ->and((float) $component->instance()->previewTotals()['subtotal_ex_vat'])->toBe(540.0);
});

it('dispatches document-line-merged when quantity is bumped on invoice', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $this->product->id)
        ->call('addLineFromProduct', $this->product->id)
        ->assertDispatched('document-line-merged', lineIndex: 0);
});

it('keeps separate lines when product unit variant differs', function () {
    $catalog = Product::query()->where('is_active', true)->get();
    $first = $catalog->first();
    $second = $catalog->first(
        fn (Product $product) => $first !== null
            && (int) $product->id !== (int) $first->id
            && (int) ($product->product_unit_id ?? 0) !== (int) ($first->product_unit_id ?? 0),
    );

    if ($first === null || $second === null) {
        $lines = [
            ['product_id' => 1, 'product_unit_id' => 10, 'product_size_id' => null, 'quantity' => '1'],
            ['product_id' => 2, 'product_unit_id' => 11, 'product_size_id' => null, 'quantity' => '1'],
        ];
        $match = new Product(['product_unit_id' => 10, 'product_size_id' => null]);
        $match->id = 1;
        $differentUnit = new Product(['product_unit_id' => 99, 'product_size_id' => null]);
        $differentUnit->id = 1;

        expect(\App\Support\Invoicing\DocumentCatalogLineMerge::findInvoicingLineIndex($lines, $match))->toBe(0)
            ->and(\App\Support\Invoicing\DocumentCatalogLineMerge::findInvoicingLineIndex($lines, $differentUnit))->toBeNull();

        return;
    }

    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $first->id)
        ->call('addLineFromProduct', $second->id);

    $filled = collect($component->get('lines'))->filter(fn ($row) => filled($row['product_id'] ?? null));

    expect($filled)->toHaveCount(2);
});

it('merges duplicate picks on quotation create', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->call('addLineFromProduct', $this->product->id)
        ->call('addLineFromProduct', $this->product->id)
        ->assertSet('lines.0.quantity', '2');
});

it('caps merged quantity at available stock when tracked', function () {
    if (! ProductStockSchema::ready()) {
        $this->markTestSkipped('Product stock schema not available.');
    }

    $stocked = Product::query()->where('track_stock', true)->where('is_active', true)->first();
    if ($stocked === null) {
        $this->markTestSkipped('No stocked catalog product.');
    }

    $stocked->forceFill(['stock_quantity' => 2])->save();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $stocked->id)
        ->call('addLineFromProduct', $stocked->id)
        ->call('addLineFromProduct', $stocked->id)
        ->assertSet('lines.0.quantity', '2');
});

it('merges duplicate products on purchase lines', function () {
    if (! ProductStockSchema::ready()) {
        $this->markTestSkipped('Product stock schema not available.');
    }

    $stocked = Product::query()->where('track_stock', true)->where('is_active', true)->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CreatePurchase::class)
        ->call('addPurchaseLineFromProduct', $stocked->id)
        ->call('addPurchaseLineFromProduct', $stocked->id)
        ->assertSet('lines.0.quantity', '2');
});