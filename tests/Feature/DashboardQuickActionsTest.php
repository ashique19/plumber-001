<?php

use App\Livewire\Admin\AdminDashboard;
use App\Livewire\Catalog\MenuManager;
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

it('shows dashboard quick action buttons for permitted owner roles', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.make_invoice'), false)
        ->assertSee(__('dashboard.add_products'), false)
        ->assertSeeHtml('href="'.route('admin.invoices').'"')
        ->assertSeeHtml('href="'.route('admin.catalog', ['tab' => 'products']).'"')
        ->assertDontSeeHtml('create=product')
        ->assertDontSeeHtml(route('admin.invoices.issue', $this->business->alternateInvoiceDocumentType()->value));
});

it('hides quick actions when the user lacks the required permissions', function () {
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
        ->assertSee(__('dashboard.make_invoice'), false)
        ->assertDontSee(__('dashboard.add_products'), false);
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

it('renders bangla labels for dashboard quick actions', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['locale' => 'bn'])
        ->test(AdminDashboard::class)
        ->assertSee('ইনভয়েস', false)
        ->assertSee('পণ্য', false)
        ->assertSeeHtml('href="'.route('admin.invoices').'"')
        ->assertSeeHtml('href="'.route('admin.catalog', ['tab' => 'products']).'"');
});

it('renders arabic labels for dashboard quick actions', function () {
    app()->setLocale('ar');

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee('فاتورة', false)
        ->assertSee('المنتجات', false);
});

it('opens catalog products tab without auto-opening the product modal', function () {
    app()->setLocale('bn');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['tab' => 'products', 'locale' => 'bn'])
        ->test(MenuManager::class)
        ->assertSet('tab', 'products')
        ->assertSet('showProductForm', false)
        ->assertSee(__('catalog.add_product', [], 'bn'), false);
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