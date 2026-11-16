<?php

use App\Models\Product;
use App\Models\ProductUnit;
use App\Support\Catalog\CatalogUnitsSizesSeed;
use App\Support\Catalog\ProductSkuGenerator;
use App\Support\Catalog\UblUnitCode;
use Database\Seeders\CatalogUnitsSizesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds units and sizes and backfills products', function (): void {
    $this->seed(CatalogUnitsSizesSeeder::class);

    expect(ProductUnit::query()->where('code', 'PCS')->exists())->toBeTrue();
    expect(\App\Models\ProductSize::query()->count())->toBe(count(CatalogUnitsSizesSeed::sizes()));

    $product = Product::query()->create([
        'name_en' => 'Test Pipe',
        'name_ar' => null,
        'name_bn' => null,
        'sku' => null,
        'price_ex_vat' => 10,
        'vat_rate' => 15,
        'is_active' => true,
    ]);

    $this->seed(CatalogUnitsSizesSeeder::class);

    $product->refresh();
    expect($product->sku)->toMatch('/^PRD-\d{3}$/');
    expect($product->product_unit_id)->not->toBeNull();
    expect($product->name_i18n_review)->toBeTrue();
});

it('generates unique sequential skus', function (): void {
    $this->seed(CatalogUnitsSizesSeeder::class);

    $first = ProductSkuGenerator::next();
    $second = ProductSkuGenerator::next();

    expect($first)->not->toBe($second);
    expect($first)->toBe('PRD-001');
});

it('maps catalog unit codes to ubl codes', function (): void {
    expect(UblUnitCode::forCatalogCode('PCS'))->toBe('PCE');
    expect(UblUnitCode::forCatalogCode('M'))->toBe('MTR');
    expect(UblUnitCode::forCatalogCode('KG'))->toBe('KGM');
});