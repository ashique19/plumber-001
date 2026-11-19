<?php

use App\Livewire\Customers\CustomerHub;
use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('allows simplified invoice quick customer save with name only', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('customerQuickName', 'Walk-in Patron')
        ->call('saveQuickCustomer')
        ->assertHasNoErrors()
        ->assertSet('selectedCustomerId', fn ($id) => $id !== null);

    expect(Customer::query()->where('name', 'Walk-in Patron')->exists())->toBeTrue();
});

it('requires standard quick customer national address fields', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('customerQuickName', 'B2B Buyer')
        ->call('saveQuickCustomer')
        ->assertHasErrors([
            'customerQuickAddrStreet',
            'customerQuickAddrBuilding',
            'customerQuickAddrDistrict',
            'customerQuickAddrPostal',
        ]);
});

it('allows quotation quick customer save with name only', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->set('customerQuickName', 'Quote Buyer')
        ->call('saveQuickCustomer')
        ->assertHasNoErrors();
});

it('allows hub customer create with legal name only', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('startCustomer')
        ->set('customerLegalNameEn', 'Hub Minimal')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    expect(Customer::query()->where('name', 'Hub Minimal')->exists())->toBeTrue();
});

it('shows inline incomplete banner on standard invoice when address is missing', function () {
    $customer = Customer::query()->create([
        'name' => 'Thin Profile',
        'phone' => null,
        'email' => null,
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => ['country' => 'SA', 'national_address' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSeeHtml('data-testid="document-customer-standard-incomplete"')
        ->assertSee(__('invoices.customer_standard_incomplete'));
});

it('does not require CR for standard buyer completion when not VAT registered', function () {
    $customer = Customer::query()->create([
        'name' => 'Address Only',
        'phone' => null,
        'email' => null,
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => ['country' => 'SA', 'national_address' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->set('buyerVatRegistered', false)
        ->set('addrBuilding', '10')
        ->set('addrStreet', 'King Fahd Rd')
        ->set('addrDistrict', 'Al Olaya')
        ->set('addrCity', 'Riyadh')
        ->set('addrPostal', '11564')
        ->set('standardBuyerPersistToCustomer', true)
        ->call('confirmStandardBuyerCompletion')
        ->assertHasNoErrors()
        ->assertSet('showStandardBuyerCompletionModal', false);
});