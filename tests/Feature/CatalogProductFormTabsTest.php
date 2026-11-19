<?php

use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStockMovement;
use App\Models\ProductUnit;
use App\Models\StaffUser;
use App\Support\Catalog\QuickProductCategoryOptions;
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

it('shows Next on the basic tab and Save on the last tab', function () {
    $basic = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->assertSet('productFormTab', 'basic');

    expect($basic->instance()->productFormPrimaryLabel())->toBe(__('catalog.next'));

    $stock = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productFormTab', 'stock');

    expect($stock->instance()->productFormPrimaryLabel())->toBe(__('catalog.save'));
});

it('advances from basic to pricing when basic fields validate via submitProductForm', function () {
    $category = ProductCategory::query()->firstOrFail();
    $unitId = ProductUnit::query()->where('code', 'PCS')->value('id');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Step Product')
        ->set('productNameAr', 'منتج')
        ->set('productCategoryId', $category->id)
        ->set('productUnitId', $unitId)
        ->call('submitProductForm')
        ->assertHasNoErrors()
        ->assertSet('productFormTab', 'pricing');
});

it('keeps basic tab and errors when Next is used without required basic fields', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->call('submitProductForm')
        ->assertHasErrors(['productNameEn', 'productNameAr'])
        ->assertSet('productFormTab', 'basic');
});

it('preselects the first ordered category when starting a new product', function () {
    $expected = QuickProductCategoryOptions::defaultId();
    expect($expected)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->assertSet('productCategoryId', $expected);
});

it('switches to the basic tab when required name fields fail validation', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productFormTab', 'stock')
        ->call('saveProduct')
        ->assertHasErrors(['productNameEn'])
        ->assertSet('productFormTab', 'basic');
});

it('renders price entry mode help under the select', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->html();

    expect($html)
        ->toContain(__('catalog.price_mode'))
        ->toContain('data-testid="catalog-price-mode"')
        ->toContain(__('catalog.price_hint'))
        ->toContain('form-field__hint--after')
        ->and(__('catalog.price_ex_vat'))->toBe('VAT ছাড়া');
});

it('uses VAT in catalog price suffix and purchase cost labels', function () {
    expect(__('catalog.ex_short'))->toMatch('/VAT|ضريبة/i')
        ->and(__('catalog.purchase_price'))->toMatch('/VAT|ضريبة/i')
        ->and(__('catalog.purchase_price', [], 'en'))->toContain('excl. VAT');
});

it('renders product form section tabs in the product sheet', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->html();

    expect($html)->toContain('catalog-product-form-tabs')
        ->and($html)->toContain('catalog-product-form-basic-layout')
        ->and($html)->toContain('catalog-product-form-basic-fields')
        ->and($html)->toContain('catalog-product-form__aligned-field')
        ->and($html)->toContain('catalog-product-form__type-active-row')
        ->and($html)->toContain('catalog-product-descriptions')
        ->and($html)->toContain('form-field__hint--after')
        ->and($html)->not->toContain('quick-product-modal__name-field')
        ->and($html)->toContain('x-modal--xl')
        ->and($html)->toContain(__('catalog.product_tab_basic'))
        ->and($html)->toContain("setProductFormTab('pricing')")
        ->and($html)->toContain(__('catalog.product_tab_stock'));

    $imagePos = strpos($html, 'form-product-image-upload');
    $namePos = strpos($html, 'productNameEn');
    expect($imagePos)->toBeInt()->and($namePos)->toBeInt()->and($imagePos)->toBeLessThan($namePos);
});

it('requires opening stock when tracking inventory and records an opening movement', function () {
    $category = ProductCategory::query()->firstOrFail();
    $unitId = ProductUnit::query()->where('code', 'PCS')->value('id');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Tracked Elbow')
        ->set('productNameAr', 'كوع مخزون')
        ->set('productNameBn', 'ট্র্যাকড এলবো')
        ->set('productCategoryId', $category->id)
        ->set('productUnitId', $unitId)
        ->set('productCostInput', '12')
        ->set('productPriceInput', '20')
        ->set('productTrackStock', true)
        ->call('saveProduct')
        ->assertHasErrors(['productOpeningStock'])
        ->assertSet('productFormTab', 'stock');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Tracked Elbow')
        ->set('productNameAr', 'كوع مخزون')
        ->set('productNameBn', 'ট্র্যাকড এলবো')
        ->set('productCategoryId', $category->id)
        ->set('productUnitId', $unitId)
        ->set('productCostInput', '12')
        ->set('productPriceInput', '20')
        ->set('productTrackStock', true)
        ->set('productOpeningStock', '8')
        ->set('productLowStockThreshold', '3')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::query()->where('name_en', 'Tracked Elbow')->first();
    expect($product)->not->toBeNull()
        ->and((bool) $product->track_stock)->toBeTrue()
        ->and((float) $product->stock_quantity)->toBe(8.0)
        ->and((float) $product->low_stock_threshold)->toBe(3.0);

    expect(ProductStockMovement::query()->where('product_id', $product->id)->where('type', 'opening')->count())->toBe(1);
});

it('shows unit and size selects on the basic tab for create and edit', function () {
    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 4,
        'low_stock_threshold' => 1,
        'cost_ex_vat' => 5,
        'price_ex_vat' => 15,
        'vat_rate' => 15,
        'name_en' => 'Sized Valve',
        'name_ar' => 'صمام',
        'name_bn' => 'ভালভ',
        'product_category_id' => ProductCategory::query()->value('id'),
        'product_unit_id' => ProductUnit::query()->where('code', 'PCS')->value('id'),
        'sku' => 'PRD-889',
    ]);

    $createHtml = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productFormTab', 'basic')
        ->html();

    expect($createHtml)
        ->toContain('data-testid="product-basic-unit"')
        ->toContain('data-testid="product-basic-size"')
        ->toContain('name="productUnitId"')
        ->toContain('name="productSizeId"')
        ->toContain(__('catalog.unit'))
        ->toContain(__('catalog.size'));

    $editHtml = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct', $product->id)
        ->set('productFormTab', 'basic')
        ->html();

    expect($editHtml)
        ->toContain('data-testid="product-basic-unit"')
        ->toContain('data-testid="product-basic-size"')
        ->toContain('name="productUnitId"')
        ->toContain('name="productSizeId"');
});

it('shows current stock read-only on edit and ignores quantity changes from the product form', function () {
    $product = Product::query()->create([
        'track_stock' => true,
        'stock_quantity' => 11,
        'low_stock_threshold' => 2,
        'cost_ex_vat' => 5,
        'price_ex_vat' => 15,
        'vat_rate' => 15,
        'name_en' => 'Existing Valve',
        'name_ar' => 'صمام موجود',
        'name_bn' => 'বিদ্যমান ভালভ',
        'product_category_id' => ProductCategory::query()->value('id'),
        'product_unit_id' => ProductUnit::query()->where('code', 'PCS')->value('id'),
        'sku' => 'PRD-888',
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct', $product->id)
        ->set('productFormTab', 'stock')
        ->assertSet('productCurrentStock', '11.000')
        ->html();

    expect($html)->toContain(__('catalog.current_stock'))
        ->and($html)->toContain(__('catalog.min_stock_level'))
        ->and($html)->not->toContain('name="productOpeningStock"');

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct', $product->id)
        ->set('productCurrentStock', '99')
        ->set('productLowStockThreshold', '4')
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product->refresh();
    expect((float) $product->stock_quantity)->toBe(11.0)
        ->and((float) $product->low_stock_threshold)->toBe(4.0);
});