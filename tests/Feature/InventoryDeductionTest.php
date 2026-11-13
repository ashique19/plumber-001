<?php

use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductIngredient;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Operations\InventoryDeductionService;
use App\Services\Pos\PosOrderService;
use App\Support\Themes\PlumberThemePreset;
use App\Business\BusinessContext;
use Illuminate\Support\Facades\Hash;

it('deducts recipe ingredients when inventory service runs', function () {
    $tenant = BusinessSetting::query()->create([
        'name' => 'Inv',
        'settings' => PlumberThemePreset::settings('Inv', 'مخزون'),
    ]);
    BusinessContext::set($tenant);

    $branch = Branch::query()->create(['code' => 'b', 'name' => 'B']);
    $cashier = StaffUser::query()->create([
        'branch_id' => $branch->id,
        'name' => 'C',
        'email' => 'c@i.test',
        'password' => Hash::make('p'),
        'is_active' => true,
    ]);

    $cat = ProductCategory::query()->create(['name_en' => 'D']);
    $product = Product::query()->create([
        'product_category_id' => $cat->id,
        'name_en' => 'Latte',
        'price_ex_vat' => 10,
        'vat_rate' => 15,
    ]);

    $ingredient = Ingredient::query()->create([
        'branch_id' => $branch->id,
        'name_en' => 'Milk',
        'quantity_on_hand' => 1000,
        'reorder_level' => 100,
    ]);

    ProductIngredient::query()->create([
        'product_id' => $product->id,
        'ingredient_id' => $ingredient->id,
        'quantity_per_unit' => 200,
    ]);

    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 2);
    $order->forceFill(['status' => \App\Enums\PosOrderStatus::Paid, 'paid_at' => now()])->save();

    app(InventoryDeductionService::class)->deductForPaidOrder($order->fresh());

    expect((float) $ingredient->fresh()->quantity_on_hand)->toBe(600.0);
});
