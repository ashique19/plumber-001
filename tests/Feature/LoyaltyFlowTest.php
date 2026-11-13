<?php

use App\Enums\PosOrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use App\Support\AuthRoleProvisioner;
use App\Support\Themes\PlumberThemePreset;
use App\Business\BusinessContext;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->withoutVite();
    AuthRoleProvisioner::seedStaffRoles();
});

afterEach(fn () => BusinessContext::clear());

it('earns points and awards referrer on first paid order', function () {
    $tenant = BusinessSetting::query()->create([
        'name' => 'Loyal Co',
        'settings' => array_merge(PlumberThemePreset::settings('Loyal Co', 'لويال'), [
            'loyalty' => [
                'earn_rate' => 1,
                'referrer_bonus_points' => 30,
            ],
            'invoicing' => [
                'default_document_type' => 'simplified',
                'integration_enabled' => false,
            ],
        ]),
    ]);
    BusinessContext::set($tenant);

    $branch = Branch::query()->create(['code' => 'b1', 'name' => 'Main']);

    $referrer = Customer::query()->create([
        'name' => 'Referrer',
        'referral_code' => 'REF-A',
        'points_balance' => 0,
    ]);

    $cashier = StaffUser::query()->create([
        'branch_id' => $branch->id,
        'name' => 'C',
        'email' => 'c@loyal.test',
        'password' => Hash::make('password'),
        'is_active' => true,
    ]);
    $cashier->assignRole('cashier');

    $category = ProductCategory::query()->create(['name_en' => 'Drinks']);
    $product = Product::query()->create([
        'product_category_id' => $category->id,
        'name_en' => 'Latte',
        'price_ex_vat' => 20,
        'vat_rate' => 15,
    ]);

    $loyalty = app(LoyaltyService::class);
    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $loyalty->attachCustomerByReferral($order->fresh(), 'REF-A', '0500000001');
    $orders->addProduct($order->fresh(), $product, 1);

    $checkout = app(PosCheckoutService::class);
    $paid = $checkout->pay($order->fresh(), \App\Enums\PosPaymentMethod::Cash, $cashier);

    $customer = Customer::query()->where('phone', '0500000001')->first();
    expect($paid->status)->toBe(PosOrderStatus::Paid)
        ->and($customer->points_balance)->toBeGreaterThan(0)
        ->and($referrer->fresh()->points_balance)->toBe(30);
});

it('blocks self-referral', function () {
    $tenant = BusinessSetting::query()->create([
        'name' => 'Self',
        'settings' => PlumberThemePreset::settings('Self', 'سيلف'),
    ]);
    BusinessContext::set($tenant);

    $branch = Branch::query()->create(['code' => 'b', 'name' => 'B']);
    $orders = app(PosOrderService::class);
    $cashier = StaffUser::query()->create([
        'branch_id' => $branch->id,
        'name' => 'C',
        'email' => 'x@test',
        'password' => Hash::make('p'),
        'is_active' => true,
    ]);
    $order = $orders->openOrder($branch, $cashier);

    Customer::query()->create([
        'name' => 'Self',
        'phone' => '0501',
        'referral_code' => 'SELF1',
        'points_balance' => 0,
    ]);

    expect(fn () => app(LoyaltyService::class)->attachCustomerByReferral($order, 'SELF1', '0501'))
        ->toThrow(InvalidArgumentException::class);
});
