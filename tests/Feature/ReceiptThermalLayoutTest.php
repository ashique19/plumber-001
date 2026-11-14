<?php

use App\Enums\PosPaymentMethod;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use App\Support\AuthRoleProvisioner;
use App\Support\Pos\ReceiptThermalPresenter;
use App\Business\BusinessContext;

beforeEach(function () {
    $this->withoutVite();
    AuthRoleProvisioner::seedStaffRoles();
});

it('builds thermal presenter with sku lines and vat summary', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $product->update(['name_ar' => 'فحص تسرب', 'sku' => 'SRV-DEMO']);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 2);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Mada, $cashier);
    $paid->load(['branch', 'cashier', 'customer']);
    $invoice = $paid->invoice()->firstOrFail();

    $thermal = ReceiptThermalPresenter::compose($business, $paid, $invoice, $branch);

    expect($thermal['lines'])->toHaveCount(1)
        ->and($thermal['lines'][0]['sku'])->toBe('SRV-DEMO')
        ->and($thermal['lines'][0]['tax_code'])->toBe('S6')
        ->and($thermal['item_count'])->toEqual(2.0)
        ->and($thermal['vat_rows'])->not->toBeEmpty()
        ->and($thermal['payment_display'])->toBe(__('invoices.payment.mada'))
        ->and($thermal['cashier_name'])->toBe('Cashier');
});

it('shows masked customer block when order has customer', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $customer = Customer::query()->create([
        'name' => 'Jamal Guest',
        'phone' => '05012340244',
        'referral_code' => 'JAMAL1',
    ]);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $order->update(['customer_id' => $customer->id]);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
    $paid->load(['branch', 'cashier', 'customer']);
    $invoice = $paid->invoice()->firstOrFail();

    $thermal = ReceiptThermalPresenter::compose($business, $paid, $invoice, $branch);

    expect($thermal['customer']['phone'])->toBe('05012340244')
        ->and($thermal['customer']['name'])->toBe('Jamal Guest');
});

it('omits card auth lines when metadata is empty', function () {
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Card, $cashier);
    $paid->load(['branch', 'cashier', 'customer']);
    $invoice = $paid->invoice()->firstOrFail();

    $thermal = ReceiptThermalPresenter::compose($business, $paid, $invoice, $branch);

    expect($thermal['card'])->toBeNull();
});
