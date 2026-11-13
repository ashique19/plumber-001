<?php

use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Pos\Home;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@fancy-coffee.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('owner reaches core admin livewire surfaces', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->assertOk();

    Livewire::actingAs($this->owner, 'staff')
        ->test(Home::class)
        ->assertOk();
});

it('cashier reaches POS but not business settings', function () {
    Livewire::actingAs($this->cashier, 'staff')
        ->test(Home::class)
        ->assertOk();

    expect($this->cashier->can('business.manage'))->toBeFalse();
});
