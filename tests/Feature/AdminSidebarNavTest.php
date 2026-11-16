<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Admin\AdminSidebarMenu;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::singleton();
    Config::set('ui.admin_sidebar', true);
    $this->actingAs($this->owner, 'staff');
});

it('renders grouped admin sidebar with business header and sales section', function () {
    $html = $this->get(route('admin.dashboard', ['locale' => 'en']))->assertOk()->getContent();

    expect($html)->toContain('app-sidebar--admin')
        ->and($html)->toContain(__('admin_nav.items.dashboard', [], 'en'))
        ->and($html)->toContain(__('admin_nav.groups.sales', [], 'en'))
        ->and($html)->toContain(__('admin_nav.items.invoices', [], 'en'))
        ->and($html)->toContain('app-topbar__menu-btn')
        ->and($html)->toContain('admin-sidebar-nav__brand-name')
        ->and($html)->toContain('app-topbar__page-title')
        ->and($html)->not->toContain('app-topbar__brand-name');
});

it('highlights invoices when viewing the invoice hub', function () {
    $html = $this->get(route('admin.invoices', ['locale' => 'en']))->assertOk()->getContent();

    expect($html)->toContain(__('admin_nav.items.invoices', [], 'en'))
        ->and($html)->toContain('app-nav-link--active');
});

it('shows catalog categories as an active nested item', function () {
    $html = $this->get(route('admin.catalog', ['tab' => 'categories', 'locale' => 'en']))->assertOk()->getContent();

    expect($html)->toContain(__('admin_nav.items.categories', [], 'en'))
        ->and($html)->toContain('app-nav-link--active');
});

it('renders arabic admin nav labels when locale is ar', function () {
    $html = $this->get(route('admin.dashboard', ['locale' => 'ar']))->assertOk()->getContent();

    expect($html)->toContain(__('admin_nav.groups.sales', [], 'ar'))
        ->and($html)->toContain('dir="rtl"', false);
});