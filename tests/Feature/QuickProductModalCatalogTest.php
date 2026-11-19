<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StaffUser;
use App\Enums\ProductItemType;
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

it('preselects the first ordered category and catalog type when the quick product modal opens on standard invoice', function () {
    $expectedCategory = QuickProductCategoryOptions::defaultId();
    $expectedType = ProductItemType::cases()[0]->value;
    expect($expectedCategory)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('openQuickProductModal')
        ->assertSet('quickProductCategoryId', $expectedCategory)
        ->assertSet('quickProductItemType', $expectedType)
        ->assertSet('quickProductFormTab', 'basic');
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

it('saves from the quick modal basic tab without visiting details', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('openQuickProductModal')
        ->set('quickProductNameEn', 'Quick Basic Save')
        ->set('quickProductNameAr', 'سريع')
        ->set('quickProductPrice', '22')
        ->call('saveQuickProduct')
        ->assertHasNoErrors();

    expect(Product::query()->where('name_en', 'Quick Basic Save')->exists())->toBeTrue();
});

it('advances to details from the quick modal when basic fields validate', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('openQuickProductModal')
        ->set('quickProductNameEn', 'Tab Step')
        ->set('quickProductNameAr', 'تبويب')
        ->set('quickProductPrice', '5')
        ->call('goToQuickProductDetailsTab')
        ->assertHasNoErrors()
        ->assertSet('quickProductFormTab', 'details');
});

it('renders catalog-aligned details tab fields and body padding in the quick modal', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard', 'locale' => 'bn'])
        ->call('openQuickProductModal')
        ->set('quickProductNameEn', 'Details Tab')
        ->set('quickProductNameAr', 'تفاصيل')
        ->set('quickProductPrice', '12')
        ->call('goToQuickProductDetailsTab')
        ->html();

    expect($html)
        ->toContain('catalog-product-form__body')
        ->toContain('catalog-product-form-tabs')
        ->toContain('catalog-product-purchase-stock-row')
        ->toContain('data-testid="catalog-product-descriptions"')
        ->toContain('name="quickProductDescriptionEn"')
        ->toContain('name="quickProductCost"')
        ->toContain('name="quickProductTrackStock"');
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
        ->toContain('catalog-product-form-basic-layout')
        ->toContain('catalog-product-form__category-type-row')
        ->toContain('catalog-product-form__panels--responsive')
        ->toContain('quick-product-form-more-details')
        ->toContain('form-product-image-upload')
        ->toContain(__('catalog.product_tab_details'))
        ->toContain('data-testid="quick-product-form-primary"');
});