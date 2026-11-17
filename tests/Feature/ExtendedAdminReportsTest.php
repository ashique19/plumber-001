<?php

use App\Enums\PosOrderStatus;
use App\Livewire\Reports\OwnerAnalytics;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\PosOrder;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;
use Tests\Support\TranslationLeakScanner;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branch = Branch::query()->where('code', 'jad-main')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('renders extended profit and inventory panels per sidebar section', function () {
    PosOrder::query()->create([
        'branch_id' => $this->branch->id,
        'order_number' => 'EXT-UI-1',
        'status' => PosOrderStatus::Paid,
        'payment_method' => 'credit',
        'subtotal_ex_vat' => 50,
        'vat_total' => 7.5,
        'total_inc_vat' => 57.5,
        'line_items' => [],
        'paid_at' => now(),
    ]);

    $profitHtml = Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'profit'])
        ->test(OwnerAnalytics::class)
        ->assertSee(__('reports.vat_summary_heading'), false)
        ->assertSee(__('reports.profit_heading'), false)
        ->assertSee(__('reports.cost_unknown'), false)
        ->html();

    TranslationLeakScanner::assertHtmlHasNoLeakedKeys($profitHtml, 'profit reports');

    $inventoryHtml = Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'inventory'])
        ->test(OwnerAnalytics::class)
        ->assertSeeHtml(e(__('reports.inventory_heading')))
        ->html();

    TranslationLeakScanner::assertHtmlHasNoLeakedKeys($inventoryHtml, 'inventory reports');

    $customersHtml = Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['section' => 'customers'])
        ->test(OwnerAnalytics::class)
        ->assertSee(__('reports.dues_heading'), false)
        ->html();

    TranslationLeakScanner::assertHtmlHasNoLeakedKeys($customersHtml, 'customer reports');
});

it('exports csv with extended sections for owner', function () {
    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.reports.export', [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});