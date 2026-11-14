<?php

use App\Livewire\Admin\AdminDashboard;
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
    $issueMode = $this->business->alternateInvoiceDocumentType()->value;

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.make_invoice'), false)
        ->assertSee(__('dashboard.add_products'), false)
        ->assertSeeHtml('href="'.route('admin.invoices.issue', $issueMode).'"')
        ->assertSeeHtml('href="'.route('admin.catalog', ['create' => 'product']).'"');
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
        ->assertSee('ইনভয়েস তৈরি করুন', false)
        ->assertSee('পণ্য যোগ করুন', false);
});

it('renders arabic labels for dashboard quick actions', function () {
    app()->setLocale('ar');

    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertSee('إنشاء فاتورة', false)
        ->assertSee('إضافة منتجات', false);
});