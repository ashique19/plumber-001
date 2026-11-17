<?php

use App\Livewire\Customers\CustomerHub;
use App\Models\Customer;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    config(['features.loyalty' => false]);
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set(\App\Models\BusinessSetting::singleton());
});

afterEach(fn () => \App\Business\BusinessContext::clear());

it('hides loyalty UI on the customers hub by default', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertSee(__('customers.title'), false)
        ->assertDontSee(__('customers.title_with_loyalty'), false)
        ->assertDontSeeHtml('data-testid="customer-modal-tabs"');

    $customer = Customer::query()->where('phone', '0501000001')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('selectCustomer', $customer->id)
        ->assertSeeHtml('data-testid="customer-modal"')
        ->assertDontSeeHtml('data-testid="customer-modal-loyalty"')
        ->assertDontSee(__('customers.tab_loyalty'), false)
        ->assertDontSee(__('customers.tab_details'), false);
});

it('does not mint referral codes when loyalty is disabled', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('startCustomer')
        ->set('customerLegalNameEn', 'No Loyalty')
        ->set('customerAltIdType', 'CRN')
        ->set('customerAltIdNumber', '1010888777')
        ->set('customerAddrStreet', 'Street')
        ->set('customerAddrCity', 'Riyadh')
        ->set('customerPhone', '0508887776')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $customer = Customer::query()->where('phone', '0508887776')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->referral_code)->toBe('');
});

it('no-ops the loyalty expire console command when disabled', function () {
    $code = Artisan::call('loyalty:expire-points');
    expect($code)->toBe(0);
});

it('shows loyalty UI when the feature flag is enabled', function () {
    config(['features.loyalty' => true]);

    $customer = Customer::query()->where('phone', '0501000001')->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('selectCustomer', $customer->id)
        ->assertSeeHtml('data-testid="customer-modal-tabs"')
        ->assertSee(__('customers.tab_loyalty'), false);
});