<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use App\Livewire\Catalog\MenuManager;

afterEach(function () {
    Auth::guard('staff')->logout();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();
    $this->cashier = StaffUser::query()
        ->where('email', 'cashier@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('lets owner manage catalog products with inc vat entry', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->set('priceMode', 'inc')
        ->call('startProduct')
        ->set('productNameEn', 'Test Mocha')
        ->set('productNameAr', 'موكا تجريبي')
        ->set('productPriceInput', '23.00')
        ->set('productVatRate', 15)
        ->call('saveProduct');

    $product = Product::query()
        ->where('name_en', 'Test Mocha')
        ->first();

    expect($product)->not->toBeNull()
        ->and((float) $product->price_ex_vat)->toBe(20.0);

    if (\App\Support\Catalog\ProductStorefrontVisibility::columnsExist()) {
        expect($product->show_on_menu)->toBeTrue()
            ->and($product->show_on_homepage)->toBeFalse();
    }
});

it('forbids cashier from catalog route', function () {
    expect($this->cashier->can('inventory.manage'))->toBeFalse();
});

it('creates categories via livewire', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Pastries')
        ->call('saveCategory');

    expect(ProductCategory::query()
        ->where('name_en', 'Pastries')
        ->exists())->toBeTrue();
});
