<?php

namespace Tests\Support;

use App\Enums\PosPaymentMethod;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\StaffUser;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;

final class InstallSqlRouteFixtures
{
    private static ?PosOrder $paidOrder = null;

    private static ?Invoice $invoice = null;

    public static function reset(): void
    {
        self::$paidOrder = null;
        self::$invoice = null;
    }

    public static function paidOrder(): ?PosOrder
    {
        return self::$paidOrder ??= PosOrder::query()->where('status', 'paid')->latest('id')->first();
    }

    public static function invoice(): ?Invoice
    {
        if (self::$invoice !== null) {
            return self::$invoice;
        }

        $order = self::paidOrder();
        if ($order?->invoice_id) {
            return self::$invoice = Invoice::query()->find($order->invoice_id);
        }

        return self::$invoice = Invoice::query()->latest('id')->first();
    }

    public static function ensurePaidPosOrder(): PosOrder
    {
        $existing = self::paidOrder();
        if ($existing !== null) {
            return $existing;
        }

        $branch = Branch::query()->where('code', 'ruh-main')->first()
            ?? Branch::query()->where('is_active', true)->firstOrFail();
        $owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
        $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();

        $orders = app(PosOrderService::class);
        $checkout = app(PosCheckoutService::class);
        $order = $orders->openOrder($branch, $owner);
        $orders->addProduct($order, $product, 1);
        $paid = $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $owner);

        self::$paidOrder = $paid;
        self::$invoice = Invoice::query()->find($paid->invoice_id);

        return $paid;
    }
}
