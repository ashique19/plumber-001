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

function posTenantSetup(): array
{
    AuthRoleProvisioner::seedStaffRoles();

    $tenant = BusinessSetting::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'POS Cafe', 'settings' => PlumberThemePreset::settings('POS Cafe', 'مقهى', 'ar')],
    );

    $branch = Branch::query()->firstOrCreate(
        ['code' => 'main'],
        ['name' => 'Main', 'name_ar' => 'الرئيسي'],
    );

    $cashier = StaffUser::query()->firstOrCreate(
        ['email' => 'cashier@pos-cafe.test'],
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
        ['name_en' => 'Drinks'],
        ['name_ar' => 'مشروبات'],
    );

    $product = Product::query()->firstOrCreate(
        ['sku' => 'LATTE'],
        [
            'product_category_id' => $category->id,
            'name_en' => 'Latte',
            'name_ar' => 'لاتيه',
            'price_ex_vat' => 20,
            'vat_rate' => 15,
        ],
    );

    return ['tenant' => $tenant, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product];
}

function seedDemoPaidPosOrder(): PosOrder
{
    $branch = Branch::query()->where('code', 'jad-main')->first()
        ?? Branch::query()->firstOrFail();
    $cashier = StaffUser::query()->where('email', 'cashier@fancy-coffee.test')->firstOrFail();
    $product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();

    $orders = app(\App\Services\Pos\PosOrderService::class);
    $checkout = app(\App\Services\Pos\PosCheckoutService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);

    return $checkout->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
}
