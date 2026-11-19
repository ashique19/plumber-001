<?php

use App\Models\Product;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('SQLite-only migration regression.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    (new DemoStaffSeeder)->run();
});

it('allows products.cost_ex_vat to be null after the nullable migration', function () {
    $product = Product::query()->firstOrFail();
    $product->cost_ex_vat = null;
    $product->save();

    expect($product->fresh()->cost_ex_vat)->toBeNull();
});