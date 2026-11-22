<?php

use App\Enums\ProductItemType;
use App\Enums\VatZatcaCategory;
use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\ProductUnit;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('adds a custom line from the issue page without creating a catalog product', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->assertSee('data-testid="add-custom-line"', false)
        ->call('addCustomLine')
        ->set('lines.0.name', 'Emergency callout')
        ->set('lines.0.unit_price', '25')
        ->set('lines.0.quantity', '2')
        ->assertSet('lines.0.name', 'Emergency callout')
        ->assertSet('lines.0.is_custom', true)
        ->assertSet('lines.0.product_id', null)
        ->assertSet('lines.0.item_type', ProductItemType::Item->value)
        ->assertSet('lines.0.vat_zatca_category', VatZatcaCategory::Standard->value);

    $html = $component->html();
    expect($html)
        ->toContain('data-custom-line="1"')
        ->toContain('placeholder="'.__('invoices.custom_line_name').'"')
        ->toContain('wire:model.live="lines.0.name"')
        ->not->toContain('wire:model.live="lines.0.name_ar"')
        ->not->toContain('wire:model.live="lines.0.name_bn"')
        ->not->toContain('wire:model.live="lines.0.item_type"')
        ->not->toContain('wire:model.live="lines.0.vat_zatca_category"')
        ->toContain('wire:model="lines.0.item_type"')
        ->toContain('wire:model="lines.0.vat_zatca_category"');

    $unit = ProductUnit::query()->where('is_active', true)->first();
    expect($unit)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', 'Emergency callout')
        ->set('lines.0.unit_price', '25')
        ->set('lines.0.quantity', '2')
        ->set('lines.0.product_unit_id', $unit?->id)
        ->call('issue')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.invoices'));

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $line = $invoice->payload['lines'][0] ?? [];

    expect($invoice->isIssued())->toBeTrue()
        ->and($line['name'] ?? null)->toBe('Emergency callout')
        ->and($line['name_ar'] ?? null)->toBe('Emergency callout')
        ->and($line['name_bn'] ?? null)->toBe('Emergency callout')
        ->and($line['is_custom'] ?? false)->toBeTrue()
        ->and($line['product_id'] ?? null)->toBeNull()
        ->and($line['item_type'] ?? null)->toBe(ProductItemType::Item->value)
        ->and($line['vat_zatca_category'] ?? null)->toBe(VatZatcaCategory::Standard->value);
});

it('requires the single custom line name', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', '')
        ->set('lines.0.unit_price', '10')
        ->call('issue')
        ->assertHasErrors(['lines.0.name'])
        ->assertHasNoErrors(['lines.0.name_ar']);
});

it('shows a friendly localized custom-line error, highlights the field, and includes an inline message', function () {
    app()->setLocale('en');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', '')
        ->set('lines.0.unit_price', '10')
        ->call('issue')
        ->assertHasErrors(['lines.0.name'])
        ->assertSee('Line 1: Name is required')
        ->assertDontSee('Arabic name is required')
        ->assertDontSee('product_id is not present')
        ->html();

    expect($html)
        ->toContain('form-field__control--invalid')
        ->toContain('data-line-error="lines.0.name"')
        ->toContain('data-testid="line-field-error"');
});

it('numbers a later custom line in the validation message', function () {
    app()->setLocale('en');
    $unit = ProductUnit::query()->where('is_active', true)->first();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', 'First')
        ->set('lines.0.unit_price', '10')
        ->set('lines.0.product_unit_id', $unit?->id)
        ->call('addCustomLine')
        ->set('lines.1.name', '')
        ->set('lines.1.unit_price', '10')
        ->call('issue')
        ->assertHasErrors(['lines.1.name'])
        ->assertSee('Line 2: Name is required')
        ->assertDontSee('The নাম')
        ->assertDontSee('product_id is not present');
});
