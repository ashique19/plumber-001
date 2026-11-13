<?php

use App\Enums\PosPaymentMethod;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\PosOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use App\Support\AuthRoleProvisioner;
use App\Support\Themes\PlumberThemePreset;
use Illuminate\Support\Facades\Hash;

function posBusinessSetup(): array
{
    AuthRoleProvisioner::seedStaffRoles();

    $business = BusinessSetting::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'POS Demo Plumbing', 'settings' => PlumberThemePreset::settings('POS Demo Plumbing', 'سباكة', 'ar')],
    );

    $branch = Branch::query()->firstOrCreate(
        ['code' => 'main'],
        ['name' => 'Main', 'name_ar' => 'الرئيسي'],
    );

    $cashier = StaffUser::query()->firstOrCreate(
        ['email' => 'cashier@pos-demo.test'],
        [
            'name' => 'Cashier',
            'password' => Hash::make('password'),
            'branch_id' => $branch->id,
            'is_active' => true,
        ],
    );
    if (! $cashier->hasRole('cashier')) {
        $cashier->assignRole('cashier');
    }

    $category = ProductCategory::query()->firstOrCreate(
        ['name_en' => 'Services'],
        ['name_ar' => 'خدمات'],
    );

    $product = Product::query()->firstOrCreate(
        ['sku' => 'SRV-DEMO'],
        [
            'product_category_id' => $category->id,
            'name_en' => 'Leak check',
            'name_ar' => 'فحص تسرب',
            'price_ex_vat' => 20,
            'vat_rate' => 15,
        ],
    );

    return ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product];
}

function seedDemoPaidPosOrder(): PosOrder
{
    $branch = Branch::query()->where('code', 'jad-main')->first()
        ?? Branch::query()->firstOrFail();
    $cashier = StaffUser::query()->where('email', 'cashier@example.test')->firstOrFail();
    $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();

    $orders = app(\App\Services\Pos\PosOrderService::class);
    $checkout = app(\App\Services\Pos\PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);

    return $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
}
