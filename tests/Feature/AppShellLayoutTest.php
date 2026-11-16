<?php

use App\Business\BusinessContext;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->actingAs($this->owner, 'staff');
    Config::set('ui.admin_sidebar', true);
});

it('hides the desktop admin sidebar when ui.admin_sidebar is false', function () {
    Config::set('ui.admin_sidebar', false);

    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('app-sidebar--admin')
        ->and($html)->toContain('app-main');
});

it('renders the structured admin sidebar by default', function () {
    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('app-sidebar--admin')
        ->and($html)->toContain('admin-sidebar-nav');
});

it('links business name to dashboard and shows localized logout in top nav', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $html = $this->get(route('admin.dashboard', ['locale' => $locale]))->assertOk()->getContent();

        expect($html)->toContain(route('admin.dashboard'))
            ->and($html)->toContain(__('pos.logout', [], $locale))
            ->and($html)->toContain('name="_token"')
            ->and($html)->toContain(route('admin.logout'));
    }
});

it('uses the mobile nav drawer instead of the legacy tab bar', function () {
    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('app-tabbar')
        ->and($html)->not->toContain(__('pos.nav_more'))
        ->and($html)->toContain('data-mobile-tabbar="0"')
        ->and($html)->toContain('app-nav-drawer')
        ->and($html)->toContain('dashboard-action-board');
});

it('renders compact top bar controls on a 360px-class layout', function () {
    $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();

    expect($html)->toContain('app-topbar__inner')
        ->and($html)->toContain('app-topbar__menu-btn')
        ->and($html)->toContain('app-topbar__logout--compact');
});