<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use App\Support\Catalog\QuickProductCategoryOptions;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('preselects the first ordered category when the quick product modal opens on standard invoice', function () {
    $expected = QuickProductCategoryOptions::defaultId();
    expect($expected)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('openQuickProductModal')
        ->assertSet('quickProductCategoryId', $expected);
});

it('preselects the first ordered category when the quick product modal opens on quotation create', function () {
    $expected = QuickProductCategoryOptions::defaultId();

    Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->call('openQuickProductModal')
        ->assertSet('quickProductCategoryId', $expected);
});

it('keeps category blank when no categories exist', function () {
    Product::query()->update(['product_category_id' => null]);
    ProductCategory::query()->delete();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('openQuickProductModal')
        ->assertSet('quickProductCategoryId', null);
});

it('persists an optional product image from the quick modal on simplified invoice', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('quickProductNameEn', 'Photo Line Item')
        ->set('quickProductNameAr', 'منتج صورة')
        ->set('quickProductPrice', '40')
        ->set('quickProductImageUpload', UploadedFile::fake()->image('quick.png', 200, 200))
        ->call('saveQuickProduct')
        ->assertHasNoErrors()
        ->assertSet('lines.0.product_image_url', fn ($url) => filled($url));

    $product = Product::query()->where('name_en', 'Photo Line Item')->firstOrFail();
    expect($product->image_url)->toStartWith('uploads/products/')
        ->and(File::exists(public_path((string) $product->image_url)))->toBeTrue();
});

it('renders quick product image upload markup in the modal', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('openQuickProductModal')
        ->html();

    expect($html)
        ->toContain('data-testid="quick-product-image-field"')
        ->toContain('data-testid="quick-product-image-input"')
        ->toContain(__('catalog.product_image.optional_hint'));
});