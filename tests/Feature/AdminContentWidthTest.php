<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('constrains admin main content and allows wider hub pages', function () {
    $dashboardHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($dashboardHtml)->toContain('app-content')
        ->and($dashboardHtml)->toContain('dashboard-action-grid');

    $customersHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.customers'))
        ->assertOk()
        ->getContent();

    expect($customersHtml)->toContain('admin-hub-page');
});