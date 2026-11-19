<?php

use App\Livewire\Catalog\MenuManager;
use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->tenant);
});

it('saves a catalog product without purchase cost', function () {
    $category = ProductCategory::query()->firstOrFail();
    $unitId = ProductUnit::query()->where('code', 'PCS')->value('id');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'No Cost Part')
        ->set('productNameAr', 'بدون تكلفة')
        ->set('productCategoryId', $category->id)
        ->set('productUnitId', $unitId)
        ->set('productPriceInput', '25')
        ->set('productCostInput', '')
        ->call('submitProductForm')
        ->assertHasNoErrors();

    $product = Product::query()->where('name_en', 'No Cost Part')->first();
    expect($product)->not->toBeNull()
        ->and($product->cost_ex_vat)->toBeNull()
        ->and($product->hasKnownCostExVat())->toBeFalse();
});

it('saves quick-add invoice product without purchase cost', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('openQuickProductModal')
        ->set('quickProductNameEn', 'Quick No Cost')
        ->set('quickProductNameAr', 'سريع')
        ->set('quickProductPrice', '40')
        ->set('quickProductCost', '')
        ->call('saveQuickProduct')
        ->assertHasNoErrors();

    $product = Product::query()->where('name_en', 'Quick No Cost')->first();
    expect($product)->not->toBeNull()
        ->and($product->cost_ex_vat)->toBeNull();
});

it('saves from the basic tab when purchase cost on details is empty', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Step No Cost')
        ->set('productNameAr', 'خطوة')
        ->set('productPriceInput', '10')
        ->set('productCostInput', '')
        ->call('submitProductForm')
        ->assertHasNoErrors()
        ->assertSet('productFormTab', 'basic');

    $product = Product::query()->where('name_en', 'Step No Cost')->first();
    expect($product)->not->toBeNull()
        ->and($product->cost_ex_vat)->toBeNull();
});