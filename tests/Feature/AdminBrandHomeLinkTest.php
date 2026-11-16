<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\StaffBranchContext;
use App\Support\StaffLandingRoute;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    \App\Business\BusinessContext::set($this->business);
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
});

it('shows sidebar brand and page title in admin topbar for dashboard access', function () {
    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('admin-sidebar-nav__brand')
        ->and($html)->toContain('app-topbar__page-title')
        ->and($html)->not->toContain('app-topbar__brand app-brand-home');
});

it('links brand chrome to pos home for cashiers without dashboard access', function () {
    $landingUrl = StaffLandingRoute::forUser($this->business, $this->cashier);

    $html = $this->actingAs($this->cashier, 'staff')
        ->get(StaffBranchContext::posHomeUrl($this->cashier))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('app-topbar__page-title')
        ->and($landingUrl)->not->toBe(route('admin.dashboard'));
});