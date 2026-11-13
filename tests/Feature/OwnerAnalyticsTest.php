<?php

use App\Livewire\Reports\OwnerAnalytics;
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
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@fancy-coffee.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('allows owner to view analytics', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(OwnerAnalytics::class)
        ->assertSee(__('reports.title'), false);
});

it('forbids cashier from analytics routes', function () {
    expect($this->cashier->can('reports.view'))->toBeFalse();
});
