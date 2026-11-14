<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Product;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('applies catalog product to a simplified invoice line', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('applyCatalogProduct', 0, $this->product->id)
        ->assertSet('lines.0.name', 'Leak repair visit')
        ->assertSet('lines.0.name_ar', 'زيارة إصلاح تسرب')
        ->assertSet('lines.0.unit_price', (string) $this->product->price_ex_vat)
        ->assertSet('lines.0.vat_rate', (string) $this->product->vat_rate)
        ->assertSet('lines.0.product_id', $this->product->id)
        ->assertDispatched('toast', type: 'info');
});

it('recalculates preview totals when catalog line is added', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('applyCatalogProduct', 0, $this->product->id);

    $totals = $component->instance()->previewTotals();

    expect($totals['subtotal_ex_vat'])->toBe(180.0)
        ->and($totals['total_vat'])->toBe(27.0)
        ->and($totals['total_inc_vat'])->toBe(207.0);
});

it('renders catalog picker on standard issue page', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->html();

    expect($html)->toContain('invoice-catalog-picker')
        ->and($html)->toContain(__('invoices.pick_catalog_product', locale: 'bn'))
        ->and($html)->toContain(__('invoices.preview_totals', locale: 'bn'));
});

it('issues simplified invoice after picking a catalog product line', function () {
    $before = Invoice::query()->count();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('applyCatalogProduct', 0, $this->product->id)
        ->call('issue')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Invoice::query()->count())->toBe($before + 1);

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    expect($invoice->payload['lines'][0]['name'] ?? null)->toBe('Leak repair visit')
        ->and((float) ($invoice->payload['lines'][0]['unit_price_ex_vat'] ?? 0))->toBe(180.0);
});

it('renders collapsed more section on simplified issue form', function () {
    app()->setLocale('en');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)->toContain(__('invoices.more_options'))
        ->and($html)->toContain('issue-simplified-more')
        ->and($html)->toContain('moreOpen: false');
});

it('issues simplified invoice with only picker price and quantity', function () {
    $before = Invoice::query()->count();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('applyCatalogProduct', 0, $this->product->id)
        ->set('lines.0.quantity', '2')
        ->call('issue')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success');

    expect(Invoice::query()->count())->toBe($before + 1);

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    expect((float) ($invoice->payload['lines'][0]['quantity'] ?? 0))->toBe(2.0)
        ->and((float) ($invoice->payload['lines'][0]['unit_price_ex_vat'] ?? 0))->toBe(180.0);
});