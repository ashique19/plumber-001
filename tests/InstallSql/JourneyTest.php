<?php

use App\Business\BusinessContext;
use App\Enums\PosOrderStatus;
use App\Livewire\Admin\BusinessSettings;
use App\Livewire\Catalog\InventoryManager;
use App\Livewire\Catalog\MenuManager;
use App\Livewire\Customers\CustomerHub;
use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Operations\TeamHub;
use App\Livewire\Pos\OrderHistory;
use App\Livewire\Pos\Terminal;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StaffUser;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\InstallSqlMysqlTestEnvironment;
use Tests\Support\InstallSqlRouteFixtures;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();

    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; install.sql journeys require MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    $this->business = BusinessSetting::singleton();
    BusinessContext::set($this->business);
    $this->branch = Branch::query()->where('code', 'ruh-main')->firstOrFail();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

afterEach(function () {
    Config::set('database.default', 'sqlite');
    DB::purge('mysql');
    DB::purge('sqlite');
    BusinessContext::clear();
});

it('runs POS terminal add, quantity change, remove, and pay on install.sql schema', function () {
    $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
    $part = Product::query()->where('sku', 'PTFE')->firstOrFail();

    $terminal = Livewire::actingAs($this->owner, 'staff')
        ->test(Terminal::class, ['branch' => $this->branch])
        ->call('addProduct', $product->id)
        ->call('addProduct', $part->id)
        ->call('incrementLine', 0)
        ->call('decrementLine', 1)
        ->call('removeLine', 1);

    $orderId = (int) $terminal->get('orderId');
    expect($orderId)->toBeGreaterThan(0);

    $terminal
        ->set('paymentMethod', 'cash')
        ->call('pay')
        ->assertRedirect();

    $order = \App\Models\PosOrder::query()->findOrFail($orderId);
    expect($order->status)->toBe(PosOrderStatus::Paid)
        ->and($order->invoice_id)->not->toBeNull();
});

it('refunds a paid order from history on install.sql schema', function () {
    $product = Product::query()->where('sku', 'SRV-DRAIN')->firstOrFail();
    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($this->branch, $this->owner);
    $orders->addProduct($order, $product, 1);
    $order = $checkout->pay($order->fresh(), \App\Enums\PosPaymentMethod::Cash, $this->owner);

    Livewire::actingAs($this->owner, 'staff')
        ->test(OrderHistory::class, ['branch' => $this->branch])
        ->call('openRefundModal', $order->id)
        ->set('refundReason', 'Install.sql smoke refund')
        ->call('confirmRefund')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect($order->fresh()->status)->toBe(PosOrderStatus::Refunded);
});

it('creates catalog products and categories on install.sql schema', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Install SQL Cat')
        ->call('saveCategory')
        ->call('startProduct')
        ->set('productNameEn', 'Install SQL Service')
        ->set('productPriceInput', '115.00')
        ->set('productVatRate', 15)
        ->call('saveProduct')
        ->assertHasNoErrors();

    expect(Product::query()->where('name_en', 'Install SQL Service')->exists())->toBeTrue();
});

it('loads customer hub and inventory manager on install.sql schema', function () {
    Customer::query()->firstOrCreate(
        ['phone' => '0501999888'],
        ['name' => 'Install SQL Customer', 'referral_code' => 'SQLSMOKE1'],
    );

    Livewire::actingAs($this->owner, 'staff')
        ->test(CustomerHub::class)
        ->assertOk();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InventoryManager::class)
        ->assertOk();
});

it('lists invoices and serves print surfaces on install.sql schema', function () {
    $invoice = InstallSqlRouteFixtures::invoice() ?? Invoice::query()->firstOrFail();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->assertSee($invoice->document_number, false);

    $order = InstallSqlRouteFixtures::paidOrder();
    expect($order)->not->toBeNull();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [$invoice]))
        ->assertOk();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.pos.receipt', ['order' => $order, 'locale' => 'ar']))
        ->assertOk();
});

it('saves business settings tabs on install.sql schema', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966 55 000 1234')
        ->call('saveContact')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'hours')
        ->call('saveHours')
        ->assertHasNoErrors();
});

it('loads team hub on install.sql schema', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(TeamHub::class)
        ->assertOk();
});

it('switches locales on the login page without error', function () {
    foreach (['en', 'ar', 'bn', 'ur'] as $locale) {
        $this->get('/?locale='.$locale)
            ->assertOk()
            ->assertStatus(200);
    }
});

it('accepts forgot-password posts without server error', function () {
    $this->post(route('password.email'), ['email' => 'owner@example.test'])
        ->assertSessionHasNoErrors()
        ->assertStatus(302);
});

it('exports reports csv for owner on install.sql schema', function () {
    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.reports.export'))
        ->assertOk();
});
