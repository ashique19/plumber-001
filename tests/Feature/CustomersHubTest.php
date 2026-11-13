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

it('lists seeded customers for owner', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertSee('Regular Customer', false)
        ->assertSee('PLUMB100', false);
});

it('denies cashier access', function () {
    expect($this->cashier->can('reports.view'))->toBeFalse();
});

it('adjusts points with audit row', function () {
    $customer = Customer::query()
        ->where('phone', '0501000001')
        ->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->call('selectCustomer', $customer->id)
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
        ->set('customerName', 'Walk-in VIP')
        ->set('customerPhone', '0509998877')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $customer = Customer::query()
        ->where('phone', '0509998877')
        ->first();

    expect($customer)->not->toBeNull()
        ->and($customer->referral_code)->not->toBe('');
});
