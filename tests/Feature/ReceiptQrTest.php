<?php

use App\Enums\PosPaymentMethod;
use App\Models\PosOrder;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use App\Support\Pos\ReceiptThermalPresenter;
use App\Support\ReceiptSigningStatusLabel;
use App\Business\BusinessContext;

beforeEach(function () {
    $this->withoutVite();
    \App\Support\AuthRoleProvisioner::seedStaffRoles();
});

it('composes thermal receipt with simplified invoice labels', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
    $invoice = $paid->invoice()->firstOrFail();

    $thermal = ReceiptThermalPresenter::compose($tenant, $paid, $invoice, $branch);
    $compliance = ReceiptSigningStatusLabel::forInvoice($invoice);

    expect($thermal['is_simplified'])->toBeTrue()
        ->and($thermal['trn'])->not->toBeNull()
        ->and($invoice->qr_tlv_base64)->not->toBeEmpty()
        ->and($compliance)->not->toBe('');
});

it('keeps TLV payload stable for issued invoices', function () {
    ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posTenantSetup();
    BusinessContext::set($tenant);

    $orders = app(PosOrderService::class);
    $checkout = app(PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);

    $paid = PosOrder::query()->findOrFail($paid->id);
    $qr = $paid->invoice()->firstOrFail()->qr_tlv_base64;

    expect($qr)->not->toBeEmpty()
        ->and(base64_encode(base64_decode($qr, true)))->toBe($qr);
});
