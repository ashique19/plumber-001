<?php

use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use App\Support\Catalog\ProductCategorySchema;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

it('builds a category payload with null arabic when only english and bangla are set', function () {
    $payload = ProductCategorySchema::mutationPayload([
        'categoryNameEn' => 'Emergency',
        'categoryNameAr' => '',
        'categoryNameBn' => 'জরুরি',
        'categoryDescriptionEn' => '',
        'categoryDescriptionAr' => '',
        'categoryDescriptionBn' => '',
        'categoryIconKey' => 'wrench',
        'categoryActive' => true,
    ]);

    expect($payload['name_en'])->toBe('Emergency')
        ->and($payload['name_ar'])->toBeNull()
        ->and($payload['name_bn'])->toBe('জরুরি');
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('creates and updates a category with english and bangla only', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory')
        ->set('categoryNameEn', 'Pipe fittings')
        ->set('categoryNameBn', 'পাইপ ফিটিং')
        ->call('saveCategory')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $category = ProductCategory::query()->where('name_en', 'Pipe fittings')->firstOrFail();
    expect($category->name_bn)->toBe('পাইপ ফিটিং')
        ->and($category->name_ar)->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(MenuManager::class)
        ->call('startCategory', $category->id)
        ->set('categoryNameBn', 'আপডেটেড')
        ->call('saveCategory')
        ->assertHasNoErrors();

    expect($category->fresh()->name_bn)->toBe('আপডেটেড');
});