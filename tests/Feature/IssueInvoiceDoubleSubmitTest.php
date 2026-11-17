<?php

use App\Business\BusinessContext;
use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Product;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('does not wire both form submit and wire click on issue buttons', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)
        ->not->toContain('primary-wire-click="issue"')
        ->not->toContain('wire-click="issue"')
        ->toContain('wire:submit="issue"');
});

it('creates only one issued invoice when issue is called twice with the same submission token', function () {
    $issuedBefore = Invoice::query()->where('status', \App\Enums\InvoiceStatus::Issued)->count();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->call('issue');

    $issuedAfter = Invoice::query()->where('status', \App\Enums\InvoiceStatus::Issued)->count();

    expect($issuedAfter)->toBe($issuedBefore + 1);
});