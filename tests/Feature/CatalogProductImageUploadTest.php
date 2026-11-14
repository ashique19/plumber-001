<?php

use App\Livewire\Catalog\MenuManager;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\StaffUser;
use App\Support\Upload\ProductImageConstraints;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->tenant = BusinessSetting::singleton();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@example.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
    \App\Business\BusinessContext::set($this->tenant);
});

it('maps oversize product image rule to a localized message', function () {
    $file = UploadedFile::fake()->create('photo.png', 4096, 'image/png');
    $validator = validator(
        ['productImageUpload' => $file],
        ['productImageUpload' => ProductImageConstraints::componentRules()],
        ProductImageConstraints::validationMessages(),
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('productImageUpload'))
        ->toBe(__('catalog.product_image.errors.too_large'));
});

it('rejects tiny product image dimensions on upload', function () {
    $component = Livewire::test(MenuManager::class)
        ->call('startProduct')
        ->set('productImageUpload', UploadedFile::fake()->image('tiny.png', 32, 32));

    $msg = $component->errors()->get('productImageUpload')[0] ?? '';

    expect($msg)->toBe(__('catalog.product_image.errors.too_small', ['min' => ProductImageConstraints::MIN_WIDTH]));
});

it('persists cropped product image path on save', function () {
    Livewire::test(MenuManager::class)
        ->call('startProduct')
        ->set('productNameEn', 'Photo Latte')
        ->set('productNameAr', 'لاتيه')
        ->set('productPriceInput', '12')
        ->set('productImageUpload', UploadedFile::fake()->image('latte.png', 400, 400))
        ->call('saveProduct')
        ->assertHasNoErrors();

    $product = Product::query()->where('name_en', 'Photo Latte')->firstOrFail();
    expect($product->image_url)->not->toBeNull()
        ->and($product->image_url)->toStartWith('uploads/products/')
        ->and(File::exists(public_path($product->image_url)))->toBeTrue();
});

it('does not render image path text field in product form', function () {
    $html = Livewire::test(MenuManager::class)
        ->call('startProduct')
        ->html();

    expect($html)->not->toContain('productImageUrl')
        ->and($html)->not->toContain('images/catalog/service-call.webp');
});

it('product image blade wires upload progress listeners', function () {
    $blade = file_get_contents(resource_path('views/components/form/product-image-upload.blade.php'));

    expect($blade)->toContain('form-logo-upload__progress')
        ->and($blade)->toContain('catalogProductImageUpload');
});

it('dispatches clear event when product image is removed', function () {
    $product = Product::query()->firstOrFail();
    $relative = 'uploads/products/'.$this->tenant->id.'/seed-test.webp';
    $dir = public_path(dirname($relative));
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true, true);
    }
    File::put(public_path($relative), 'x');
    $product->update(['image_url' => $relative]);

    Livewire::test(MenuManager::class)
        ->call('startProduct', $product->id)
        ->call('removeProductImage')
        ->assertSet('productImageRemove', true)
        ->assertDispatched('catalog-product-image-cleared');
});