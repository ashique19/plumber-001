<?php

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
        ->set('lines.0.name_ar', 'زيارة طارئة')
        ->set('lines.0.name_bn', 'জরুরি ভিজিট')
        ->set('lines.0.unit_price', '25')
        ->set('lines.0.quantity', '2')
        ->assertSet('lines.0.name', 'Emergency callout')
        ->assertSet('lines.0.is_custom', true)
        ->assertSet('lines.0.product_id', null);

    expect($component->html())->toContain('data-custom-line="1"');

    $unit = ProductUnit::query()->where('is_active', true)->first();
    expect($unit)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', 'Emergency callout')
        ->set('lines.0.name_ar', 'زيارة طارئة')
        ->set('lines.0.name_bn', 'জরুরি ভিজিট')
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
        ->and($line['name_ar'] ?? null)->toBe('زيارة طارئة')
        ->and($line['is_custom'] ?? false)->toBeTrue()
        ->and($line['product_id'] ?? null)->toBeNull();
});

it('requires english and arabic names on a custom line', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addCustomLine')
        ->set('lines.0.name', '')
        ->set('lines.0.name_ar', '')
        ->set('lines.0.unit_price', '10')
        ->call('issue')
        ->assertHasErrors(['lines.0.name']);
});
