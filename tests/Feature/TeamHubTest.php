<?php

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Operations\ShiftService;
use Database\Seeders\DemoFancyCoffeeSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use App\Livewire\Operations\TeamHub;

afterEach(function () {
    Auth::guard('staff')->logout();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoFancyCoffeeSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->branch = Branch::query()
        ->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@fancy-coffee.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('allows owner to manage team hub and open a shift', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->set('tab', 'shifts')
        ->set('shiftBranchId', $this->branch->id)
        ->set('openingFloat', '500')
        ->call('openShift');

    expect(app(ShiftService::class)->openShiftForBranch($this->branch))->not->toBeNull();
});

it('forbids cashier from team hub', function () {
    expect($this->cashier->can('staff.manage'))->toBeFalse();
});
