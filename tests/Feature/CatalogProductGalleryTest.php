<?php

use App\Enums\ProductItemType;
use App\Livewire\Catalog\MenuManager;
use App\Models\Product;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('paginates catalog products and shows gallery markup', function () {
  Product::query()->delete();

  for ($i = 1; $i <= 30; $i++) {
      Product::query()->create([
          'name_en' => "Gallery Product {$i}",
          'name_ar' => "منتج {$i}",
          'price_ex_vat' => 10 + $i,
          'vat_rate' => 15,
          'is_active' => true,
          'item_type' => $i % 3 === 0 ? ProductItemType::Service : ProductItemType::Item,
      ]);
  }

  Livewire::actingAs($this->owner, 'staff')
      ->test(MenuManager::class)
      ->assertSee('data-testid="catalog-product-gallery"', false)
      ->assertSeeHtml('catalog-product-gallery__grid')
      ->assertSet('productsView', 'gallery')
      ->call('setProductsView', 'list')
      ->assertSee('data-testid="catalog-product-list"', false);
});

it('filters services in the gallery kind chip', function () {
  Product::query()->delete();
  Product::query()->create([
      'name_en' => 'Pipe fitting',
      'name_ar' => 'تركيب',
      'price_ex_vat' => 50,
      'vat_rate' => 15,
      'item_type' => ProductItemType::Item,
      'is_active' => true,
  ]);
  Product::query()->create([
      'name_en' => 'Emergency call-out',
      'name_ar' => 'طوارئ',
      'price_ex_vat' => 120,
      'vat_rate' => 15,
      'item_type' => ProductItemType::Service,
      'is_active' => true,
  ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->set('productKind', 'service')
        ->assertSee('Emergency call-out', false)
        ->assertDontSee('Pipe fitting', false);
});

it('opens the product action modal when a tile is activated', function () {
  $product = Product::query()->firstOrFail();

  Livewire::actingAs($this->owner, 'staff')
      ->test(MenuManager::class)
      ->call('openProductActions', $product->id)
      ->assertSet('showProductActionModal', true)
      ->assertSee('data-testid="catalog-product-action-modal"', false)
      ->assertSee(__('catalog.action_add_to_invoice'), false);
});