<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\Customer;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentCreateUiSupport;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->customer = Customer::query()->orderBy('id')->firstOrFail();
});

it('infers per-invoice contact override when payload differs from customer record', function () {
    $this->customer->forceFill(['phone' => '0500000001', 'email' => 'on-file@example.test'])->save();

    expect(DocumentCreateUiSupport::shouldInferBuyerContactOverride($this->customer->id, '0500000001', 'on-file@example.test'))
        ->toBeFalse()
        ->and(DocumentCreateUiSupport::shouldInferBuyerContactOverride($this->customer->id, '0599999999', 'on-file@example.test'))
        ->toBeTrue();
});

it('hides duplicate buyer contact fields in more options after customer is linked', function () {
    $moreOptionsBlade = file_get_contents(resource_path('views/components/invoices/document-more-options.blade.php'));
    expect($moreOptionsBlade)->not->toContain('buyerPhone');

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $this->customer->id)
        ->assertSet('buyerContactOverride', false)
        ->assertSee(__('invoices.document_adjustments_heading'))
        ->assertSee(__('invoices.buyer_contact_override_toggle'));
});

it('shows walk-in optional contact on simplified issue without customer', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->assertSee(__('invoices.buyer_contact_walk_in_heading'))
        ->html();

    expect($html)->toContain('name="buyerPhone"');
});

it('reveals editable buyer contact when override toggle is enabled', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->call('selectDocumentCustomer', $this->customer->id)
        ->set('buyerContactOverride', true)
        ->html();

    expect($html)->toContain('name="buyerPhone"');
});