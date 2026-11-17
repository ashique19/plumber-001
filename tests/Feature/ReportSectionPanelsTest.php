<?php

use App\Enums\PosOrderStatus;
use App\Livewire\Reports\OwnerAnalytics;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\PosOrder;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);

    PosOrder::query()->create([
        'branch_id' => $this->branch->id,
        'order_number' => 'SEC-PANEL-1',
        'status' => PosOrderStatus::Paid,
        'payment_method' => 'credit',
        'subtotal_ex_vat' => 50,
        'vat_total' => 7.5,
        'total_inc_vat' => 57.5,
        'line_items' => [],
        'paid_at' => now(),
    ]);
});

it('renders sales charts without extended panels by default', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(OwnerAnalytics::class)
        ->assertSee(__('reports.sales_over_time'), false)
        ->assertDontSee(__('reports.profit_heading'), false)
        ->assertDontSee(__('reports.inventory_heading'), false)
        ->assertDontSee(__('reports.dues_heading'), false);
});

it('renders profit panels when section is profit', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'profit'])
        ->test(OwnerAnalytics::class)
        ->assertSeeHtml(e(__('reports.title_profit')))
        ->assertSee(__('reports.vat_summary_heading'), false)
        ->assertSee(__('reports.profit_heading'), false)
        ->assertDontSee(__('reports.sales_over_time'), false)
        ->assertDontSee(__('reports.inventory_heading'), false);
});

it('renders inventory panel when section is inventory', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'inventory'])
        ->test(OwnerAnalytics::class)
        ->assertSeeHtml(e(__('reports.title_inventory')))
        ->assertSeeHtml(e(__('reports.inventory_heading')))
        ->assertDontSee(__('reports.sales_over_time'), false)
        ->assertDontSee(__('reports.profit_heading'), false);
});

it('renders customer dues panel when section is customers', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'customers'])
        ->test(OwnerAnalytics::class)
        ->assertSee(__('reports.title_customers'), false)
        ->assertSee(__('reports.dues_heading'), false)
        ->assertDontSee(__('reports.sales_over_time'), false)
        ->assertDontSee(__('reports.inventory_heading'), false);
});