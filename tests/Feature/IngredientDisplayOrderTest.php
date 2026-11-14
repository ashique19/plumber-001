<?php

use App\Models\Ingredient;
use App\Support\Inventory\IngredientInventorySchema;
use Illuminate\Support\Facades\Schema;

it('orders ingredients by name_en when legacy name column is absent', function () {
    expect(IngredientInventorySchema::displayNameOrderColumn())->toBe('name_en');
    expect(Schema::hasColumn('ingredients', 'name'))->toBeFalse();

    Ingredient::query()->create([
        'name_en' => 'Zinc solder',
        'unit' => 'g',
        'quantity_on_hand' => 1,
        'reorder_level' => 0,
        'low_stock_threshold' => 0,
        'is_active' => true,
    ]);
    Ingredient::query()->create([
        'name_en' => 'Alpha flux',
        'unit' => 'g',
        'quantity_on_hand' => 1,
        'reorder_level' => 0,
        'low_stock_threshold' => 0,
        'is_active' => true,
    ]);

    $names = Ingredient::query()->orderedForDisplay()->pluck('name_en')->all();

    expect($names)->toBe(['Alpha flux', 'Zinc solder']);
});