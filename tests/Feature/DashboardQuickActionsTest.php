<?php

use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Catalog\MenuManager;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
});

it('redirects staff login to the dashboard when reports access is granted', function () {
    $response = $this->post('/login', [
        'email' => $this->owner->email,
        'password' => 'TestPassword!2026',
    ]);

    $response->assertRedirect(route('admin.dashboard', absolute: false));
    $this->assertAuthenticatedAs($this->owner, 'staff');
});

it('shows eight dashboard action tiles for the owner in grid order', function () {
  $defaultMode = $this->business->defaultInvoiceDocumentType()->value;

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.tiles.create_invoice'), false)
        ->assertSee(__('dashboard.tiles.create_quotation'), false)
        ->assertSee(__('dashboard.tiles.add_product'), false)
        ->assertSee(__('dashboard.tiles.add_customer'), false)
        ->assertSee(__('dashboard.tiles.check_inventory'), false)
        ->assertSee(__('dashboard.tiles.record_purchase'), false)
        ->assertSee(__('dashboard.tiles.payments_dues'), false)
        ->assertSee(__('dashboard.tiles.view_reports'), false)
        ->assertSeeHtml('dashboard-action-grid')
        ->assertSeeHtml('href="'.route('admin.invoices.issue', ['mode' => $defaultMode]).'"')
        ->assertSeeHtml('create=product')
        ->assertSeeHtml('create=customer')
        ->assertSeeHtml('href="'.route('admin.inventory').'"')
        ->assertSeeHtml('href="'.route('admin.reports').'"')
        ->assertSee('🧾', false)
        ->assertSee(__('dashboard.tiles.subtitles.create_invoice'), false)
        ->assertSee('dashboard-action-tile--disabled', false);
});

it('hides product and purchase tiles when the user lacks catalog permissions', function () {
    $accountant = StaffUser::query()->firstOrCreate(
        ['email' => 'accountant@example.test'],
        [
            'name' => 'Accountant',
            'password' => \Illuminate\Support\Facades\Hash::make('TestPassword!2026'),
            'branch_id' => $this->owner->branch_id,
            'is_active' => true,
        ],
    );
    $accountant->syncRoles(['accountant']);

    Livewire::actingAs($accountant, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.tiles.create_invoice'), false)
        ->assertDontSee(__('dashboard.tiles.add_product'), false)
        ->assertDontSee(__('dashboard.tiles.check_inventory'), false)
        ->assertDontSee(__('dashboard.tiles.record_purchase'), false);
});

it('sends cashiers without dashboard access to pos after login', function () {
    Branch::query()->update(['is_active' => true]);
    $this->cashier->forceFill(['branch_id' => null])->save();

    $response = $this->post('/login', [
        'email' => $this->cashier->email,
        'password' => 'TestPassword!2026',
    ]);

    $response->assertRedirect(route('admin.pos.home', absolute: false));
});

it('renders bangla labels for dashboard action tiles', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['locale' => 'bn'])
        ->test(AdminDashboard::class)
        ->assertSee('ইনভয়েস তৈরি', false)
        ->assertSee('পণ্য যোগ', false)
        ->assertSee(__('dashboard.tiles.subtitles.add_product', [], 'bn'), false);
});

it('renders arabic labels for dashboard action tiles', function () {
    app()->setLocale('ar');

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee('إنشاء فاتورة', false)
        ->assertSee('إضافة منتج', false)
        ->assertSee(__('dashboard.tiles.subtitles.create_invoice', [], 'ar'), false);
});

it('opens catalog products tab and product form from dashboard product link', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['tab' => 'products', 'create' => 'product', 'locale' => 'bn'])
        ->test(MenuManager::class)
        ->assertSet('tab', 'products')
        ->assertSet('showProductForm', true)
        ->assertSee(__('catalog.add_product', [], 'bn'), false);
});

it('opens customer create sheet from dashboard customer link', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['create' => 'customer'])
        ->test(CustomerHub::class)
        ->assertSet('showCustomerForm', true)
        ->assertSee(__('customers.add'), false);
});

it('shows bangla print layout choices on the invoice hub create section', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['locale' => 'bn'])
        ->test(InvoiceHub::class)
        ->assertSee(__('invoices.create_heading', [], 'bn'), false)
        ->assertSee(__('invoices.print_a4', [], 'bn'), false)
        ->assertSee(__('invoices.print_cash', [], 'bn'), false)
        ->assertSeeHtml('href="'.route('admin.invoices.issue', ['mode' => 'standard']).'"')
        ->assertSeeHtml('href="'.route('admin.invoices.issue', ['mode' => 'simplified']).'"');
});