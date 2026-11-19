<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

function incompleteStandardCustomer(): Customer
{
    return Customer::query()->create([
        'name' => 'Incomplete Buyer',
        'phone' => '0533333333',
        'email' => null,
        'vat_number' => null,
        'vat_registered' => false,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'country' => 'SA',
            'national_address' => [],
        ],
    ]);
}

it('opens completion modal when an incomplete customer is selected for standard issue', function () {
    $customer = incompleteStandardCustomer();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSet('showStandardBuyerCompletionModal', true)
        ->assertSee(__('invoices.standard_buyer_completion_title'));
});

it('renders standard buyer completion modal with missing address fields only', function () {
    $customer = incompleteStandardCustomer();

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->html();

    expect($html)
        ->toContain('x-modal--identity')
        ->toContain('name="addrBuilding"', false)
        ->toContain('name="addrDistrict"', false)
        ->not->toContain('data-testid="customer-country-readonly"');
});

it('opens completion modal on issue when buyer fields are still missing', function () {
    $customer = incompleteStandardCustomer();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->set('showStandardBuyerCompletionModal', false)
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->assertSet('showStandardBuyerCompletionModal', true)
        ->assertHasNoErrors(['buyerVat']);
});

it('persists buyer details to the customer when save checkbox is checked', function () {
    $customer = incompleteStandardCustomer();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->set('buyerVatRegistered', false)
        ->set('buyerAltIdType', 'CRN')
        ->set('buyerAltIdNumber', '1010998899')
        ->set('addrBuilding', '12')
        ->set('addrStreet', 'King Fahd Rd')
        ->set('addrDistrict', 'Al Olaya')
        ->set('addrCity', 'Riyadh')
        ->set('addrPostal', '11564')
        ->set('standardBuyerPersistToCustomer', true)
        ->call('confirmStandardBuyerCompletion')
        ->assertSet('showStandardBuyerCompletionModal', false);

    $customer->refresh();
    expect(data_get($customer->profile, 'cr_number'))->toBe('1010998899')
        ->and(data_get($customer->profile, 'national_address.building'))->toBe('12')
        ->and(data_get($customer->profile, 'national_address.street'))->toBe('King Fahd Rd')
        ->and(data_get($customer->profile, 'national_address.postal_code'))->toBe('11564');
});

it('issues without updating the customer when save checkbox is unchecked', function () {
    $customer = incompleteStandardCustomer();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->set('showStandardBuyerCompletionModal', false)
        ->call('addLineFromProduct', $this->product->id)
        ->set('buyerVatRegistered', false)
        ->set('buyerAltIdType', 'CRN')
        ->set('buyerAltIdNumber', '1010998899')
        ->set('addrBuilding', '12')
        ->set('addrStreet', 'King Fahd Rd')
        ->set('addrDistrict', 'Al Olaya')
        ->set('addrCity', 'Riyadh')
        ->set('addrPostal', '11564')
        ->set('standardBuyerPersistToCustomer', false)
        ->set('resumeIssueAfterBuyerCompletion', true)
        ->call('confirmStandardBuyerCompletion')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.invoices'));

    $customer->refresh();
    expect($customer->vat_number)->toBeNull()
        ->and(data_get($customer->profile, 'cr_number'))->toBeNull()
        ->and(data_get($customer->profile, 'national_address.building'))->toBeNull();
});

it('still shows validation summary for non-buyer errors', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('issue')
        ->assertHasErrors(['document'])
        ->assertSee('id="document-validation-summary"', false);
});

it('closes customer results after selecting a customer', function () {
    $customer = Customer::query()->whereNotNull('phone')->orderBy('id')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->set('customerSearch', substr($customer->name, 0, 3))
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSet('selectedCustomerId', $customer->id)
        ->assertSee('data-typeahead-results-open="0"', false);
});