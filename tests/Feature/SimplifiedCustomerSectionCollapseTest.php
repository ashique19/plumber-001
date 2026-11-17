<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Customer;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('collapses customer card on simplified issue by default', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->assertSet('simplifiedCustomerExpanded', false)
        ->assertSeeHtml('data-testid="document-customer-collapsed"', false)
        ->assertSee(__('invoices.add_customer_optional'), false)
        ->assertDontSee(__('invoices.customer_information'), false)
        ->assertDontSee('name="customerSearch"', false);
});

it('expands customer picker when add customer is tapped on simplified issue', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('expandSimplifiedCustomerSection')
        ->assertSet('simplifiedCustomerExpanded', true)
        ->assertSee(__('invoices.customer_information'), false)
        ->assertSee(__('invoices.customer_search'), false);
});

it('keeps customer card open on standard issue', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->assertSee(__('invoices.customer_information'), false)
        ->assertDontSeeHtml('data-testid="document-customer-collapsed"', false);
});

it('expands customer section after selecting a customer on simplified issue', function () {
    $customer = Customer::query()->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('selectDocumentCustomer', $customer->id)
        ->assertSet('simplifiedCustomerExpanded', true)
        ->assertSee(__('invoices.customer_information'), false);
});