<?php

use App\Enums\ProductItemType;
use App\Models\Product;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('seeds plumber catalog with services and parts', function () {
    $services = Product::query()->where('item_type', ProductItemType::Service)->where('is_active', true)->get();
    $parts = Product::query()->where('item_type', ProductItemType::Part)->where('is_active', true)->get();

    expect($services)->not->toBeEmpty()
        ->and($parts)->not->toBeEmpty()
        ->and($services->firstWhere('sku', 'SRV-LEAK'))->not->toBeNull()
        ->and($parts->firstWhere('sku', 'PTFE'))->not->toBeNull();
});
