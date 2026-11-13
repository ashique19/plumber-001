<?php

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosEndOfDayService;
use App\Services\Pos\PosOrderService;
use App\Support\AuthRoleProvisioner;
use App\Business\BusinessContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Livewire\Pos\Home;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutVite();
    AuthRoleProvisioner::seedStaffRoles();
});

afterEach(function () {
    BusinessContext::clear();
    
    \Illuminate\Support\Facades\Auth::guard('staff')->logout();
});

it('pays an order and issues a simplified invoice', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    /** @var PosOrderService $orders */
    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 2);
    $orders->setPromoDiscount($order->fresh(), 5);

    /** @var PosCheckoutService $checkout */
    $checkout = app(PosCheckoutService::class);
    $paid = $checkout->pay($order->fresh(), \App\Enums\PosPaymentMethod::Card, $cashier);

    expect($paid->status)->toBe(PosOrderStatus::Paid)
        ->and($paid->invoice_id)->not->toBeNull()
        ->and($paid->payment_method)->toBe('card');
});

it('summarizes end of day totals by payment method', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);

    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $checkout->pay($order->fresh(), \App\Enums\PosPaymentMethod::Cash, $cashier);

    $summary = app(PosEndOfDayService::class)->summarize($branch);
    expect($summary['order_count'])->toBe(1)
        ->and($summary['by_payment']['cash']['count'])->toBe(1);
});

it('allows cashier login and loads POS home', function () {
    ['business' => $business, 'cashier' => $cashier] = posBusinessSetup();
    BusinessContext::set($business);

    Livewire::actingAs($cashier, 'staff')
        ->test(Home::class)
        ->assertOk();
});

it('runs livewire add and pay flow', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    Livewire::actingAs($cashier, 'staff')
        ->withQueryParams([])
        ->test(\App\Livewire\Pos\Terminal::class, ['branch' => $branch])
        ->call('addProduct', $product->id)
        ->set('paymentMethod', 'cash')
        ->call('pay')
        ->assertHasNoErrors();

    expect(\App\Models\PosOrder::query()->latest('id')->first()?->status)
        ->toBe(PosOrderStatus::Paid);
});
