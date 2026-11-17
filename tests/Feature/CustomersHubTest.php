<?php

use App\Livewire\Customers\CustomerHub;
use App\Models\Customer;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('lists seeded customers in a sortable table', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertSeeHtml('data-testid="customers-table"')
        ->assertSeeHtml('data-testid="hub-customer-row-actions"')
        ->assertSeeHtml('data-testid="hub-row-action-more"')
        ->assertSee(__('customers.col_vat'), false)
        ->assertSee(__('customers.col_cr'), false)
        ->assertSee('Regular Customer', false)
        ->assertSee('0501000001', false);
});

it('opens add customer modal from create query', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['create' => 'customer'])
        ->test(CustomerHub::class)
        ->assertSet('showCustomerModal', true)
        ->assertSee(__('customers.add'), false);
});

it('sorts customers by name when header is clicked', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('sortBy', 'name')
        ->assertSet('sort', 'name')
        ->assertSet('sortDir', 'desc');
});

it('handles sort search and startEdit without errors', function () {
    $customer = Customer::query()->where('phone', '0501000001')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('sortBy', 'email')
        ->assertHasNoErrors()
        ->set('search', '0501000')
        ->assertHasNoErrors()
        ->call('startEdit', $customer->id)
        ->assertSet('showCustomerModal', true)
        ->assertHasNoErrors();
});

it('denies cashier access', function () {
    expect($this->cashier->can('reports.view'))->toBeFalse();
});

it('renders customer modal as wide form sheet with street textarea', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('startCustomer')
        ->assertSeeHtml('x-modal--form-sheet')
        ->assertSeeHtml('customer-identity-fields--sectioned')
        ->assertSeeHtml('customer-identity-fields__span-full')
        ->assertSeeHtml('<textarea');
});

it('opens read-only detail on view and edit form on startEdit', function () {
    $customer = Customer::query()
        ->where('phone', '0501000001')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('selectCustomer', $customer->id)
        ->assertSet('showCustomerDetailModal', true)
        ->assertSet('showCustomerModal', false)
        ->assertSeeHtml('data-testid="customer-detail-modal"')
        ->assertSeeHtml('data-testid="customer-detail-quick-actions"')
        ->assertDontSeeHtml('data-testid="customer-identity-fields"')
        ->call('startEdit', $customer->id)
        ->assertSet('showCustomerModal', true)
        ->assertSet('showCustomerDetailModal', false)
        ->assertSeeHtml('data-testid="customer-identity-fields"');
});

it('shows details tab with editable address fields in edit modal when loyalty is on', function () {
    if (! config('features.loyalty')) {
        $this->markTestSkipped('Loyalty feature is disabled.');
    }

    $customer = Customer::query()
        ->where('phone', '0501000001')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('startEdit', $customer->id)
        ->assertSet('showCustomerModal', true)
        ->assertSeeHtml('data-testid="customer-modal-tabs"')
        ->assertSee(__('customers.tab_details'), false)
        ->assertSeeHtml('data-testid="customer-identity-fields"');
});

it('hides referral copy in hub header when loyalty is disabled', function () {
    config(['features.loyalty' => false]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertSee(__('customers.title_hub'), false)
        ->assertSee(__('customers.subtitle_hub'), false)
        ->assertDontSee(__('customers.subtitle_with_loyalty'), false);
});

it('adjusts points with audit row', function () {
    $customer = Customer::query()
        ->where('phone', '0501000001')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('selectCustomer', $customer->id)
        ->call('setCustomerModalTab', 'loyalty')
        ->set('adjustPoints', '10')
        ->set('adjustNote', 'Manager courtesy top-up')
        ->call('saveAdjust')
        ->assertHasNoErrors();

    $customer->refresh();
    expect($customer->points_balance)->toBe(130)
        ->and($customer->loyaltyTransactions()->where('type', 'adjust')->count())->toBe(1);
});

it('creates customers with referral codes', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('startCustomer')
        ->set('customerLegalNameEn', 'Walk-in VIP')
        ->set('customerAltIdType', 'CRN')
        ->set('customerAltIdNumber', '1010998899')
        ->set('customerAddrStreet', 'King Fahd Rd')
        ->set('customerAddrCity', 'Riyadh')
        ->set('customerPhone', '0509998877')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $customer = Customer::query()
        ->where('phone', '0509998877')
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->referral_code)->not->toBe('');
});
