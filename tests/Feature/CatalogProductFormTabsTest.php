<?php

use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
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

it('switches to the basic tab when required name fields fail validation', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->set('productFormTab', 'stock')
        ->call('saveProduct')
        ->assertHasErrors(['productNameEn'])
        ->assertSet('productFormTab', 'basic');
});

it('renders product form section tabs in the product sheet', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startProduct')
        ->html();

    expect($html)->toContain('catalog-product-form-tabs')
        ->and($html)->toContain(__('catalog.product_tab_basic'))
        ->and($html)->toContain("setProductFormTab('pricing')")
        ->and($html)->toContain(__('catalog.product_tab_stock'));
});