<?php

use App\Livewire\Admin\AdminDashboard;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('does not render the removed today average order card', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertDontSee('আজকের গড় অর্ডার', false);
});

it('does not render invoices by type or signing queue summary cards', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        Livewire::actingAs($this->owner, 'staff')
            ->withQueryParams(['locale' => $locale])
            ->test(AdminDashboard::class)
            ->assertDontSee(__('dashboard.invoices_by_type', [], $locale), false)
            ->assertDontSee(__('dashboard.invoices_issued_hint', [], $locale), false)
            ->assertDontSee(__('dashboard.signing_queue_hint', [], $locale), false);
    }
});

it('renders only quick action tiles without monthly report charts or kpi rows', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        Livewire::actingAs($this->owner, 'staff')
            ->withQueryParams(['locale' => $locale])
            ->test(AdminDashboard::class)
            ->assertSeeHtml('data-testid="admin-dashboard"')
            ->assertSeeHtml('dashboard-action-grid')
            ->assertSee(__('dashboard.tiles.create_invoice', [], $locale), false)
            ->assertDontSee(__('dashboard.monthly_report.title', [], $locale), false)
            ->assertDontSee(__('dashboard.recent_sales', [], $locale), false)
            ->assertDontSee(__('dashboard.today_orders', [], $locale), false);
    }
});