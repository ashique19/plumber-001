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

it('requires a linked customer for standard issue', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->assertHasErrors(['selectedCustomerId']);
});

it('prompts buyer completion when vat or cr is missing on the customer', function () {
    $customer = Customer::query()->create([
        'name' => 'Buyer LLC',
        'phone' => '0511111111',
        'email' => null,
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'country' => 'SA',
            'national_address' => [
                'building' => '10',
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '11564',
            ],
        ],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSet('showStandardBuyerCompletionModal', true)
        ->set('showStandardBuyerCompletionModal', false)
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->assertSet('showStandardBuyerCompletionModal', true);
});

it('passes buyer validation when cr is provided on the customer', function () {
    $customer = Customer::query()->create([
        'name' => 'Buyer LLC',
        'phone' => '0522222222',
        'email' => null,
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'cr_number' => '1010998899',
            'country' => 'SA',
            'national_address' => [
                'building' => '10',
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '11564',
            ],
        ],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id)
        ->call('addLineFromProduct', $this->product->id)
        ->call('issue')
        ->assertHasNoErrors(['buyerVat', 'buyerCr']);
});