<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\Product;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('exposes mutually exclusive responsive line layouts', function () {
    $blade = file_get_contents(resource_path('views/components/invoices/document-product-lines-create.blade.php'));

    expect($blade)
        ->toContain('document-product-line-cards lg:hidden')
        ->toContain('document-product-lines-scroll hidden lg:block');
});

it('sets line vat_rate to select-friendly value when adding a product', function () {
    $product = Product::query()->where('is_active', true)->firstOrFail();
    $product->forceFill(['vat_rate' => 15.0])->save();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('addLineFromProduct', $product->id)
        ->assertSet('lines.0.vat_rate', '15');
});

it('tints selected buyer and product line cards in compiled document create css', function () {
    $source = file_get_contents(resource_path('css/document-create-layout.css'));

    expect($source)
        ->toContain('.document-customer-strip')
        ->toContain('--theme-accent-soft')
        ->toContain('.document-product-line-card')
        ->toContain('admin-theme-dark .document-customer-strip');

    $manifestPath = public_path('build/manifest.json');
    if (! is_file($manifestPath)) {
        $this->markTestSkipped('Vite build output not present in this environment.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $cssEntry = $manifest['resources/css/app.css']['file'] ?? null;
    $css = (string) file_get_contents(public_path('build/'.$cssEntry));

    expect($css)->toContain('document-customer-strip')
        ->and($css)->toContain('document-product-line-card');
});

it('includes responsive utility classes in production css bundle', function () {
    $manifestPath = public_path('build/manifest.json');
    if (! is_file($manifestPath)) {
        $this->markTestSkipped('Vite build output not present in this environment.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $cssEntry = $manifest['resources/css/app.css']['file'] ?? null;
    expect($cssEntry)->not->toBeNull();

    $css = (string) file_get_contents(public_path('build/'.$cssEntry));
    expect($css)->toContain('lg\\:hidden')->and($css)->toContain('lg\\:block');
});