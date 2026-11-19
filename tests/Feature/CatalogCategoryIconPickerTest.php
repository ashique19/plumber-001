<?php

use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use App\Support\Catalog\CategoryIconOptions;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders the category icon picker instead of a text field', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->html();

    expect($html)
        ->toContain('data-testid="catalog-category-icon-picker"')
        ->toContain(__('catalog.category_icons.cup'))
        ->not->toContain('name="categoryIconKey" type="text"');
});

it('rejects unsupported icon keys on save', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Bad Icon Cat')
        ->set('categoryIconKey', 'not-a-real-icon')
        ->call('saveCategory')
        ->assertHasErrors(['categoryIconKey']);
});

it('normalizes unknown stored icon keys when editing without validation errors', function () {
    $category = ProductCategory::query()->firstOrFail();
    $category->update(['icon_key' => 'legacy-unknown']);

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory', $category->id)
        ->assertSet('categoryIconKey', CategoryIconOptions::DEFAULT)
        ->assertHasNoErrors();

    expect($category->fresh()->icon_key)->toBe('legacy-unknown')
        ->and(CategoryIconOptions::normalize($category->icon_key))->toBe(CategoryIconOptions::DEFAULT);
});

it('saves a category with a supported icon key from the picker', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Icon Picker Cat')
        ->set('categoryIconKey', 'wrench')
        ->call('saveCategory')
        ->assertHasNoErrors();

    $category = ProductCategory::query()->where('name_en', 'Icon Picker Cat')->firstOrFail();
    expect($category->icon_key)->toBe('wrench');
});