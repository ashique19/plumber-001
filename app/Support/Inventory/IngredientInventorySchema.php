<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\Schema;

final class IngredientInventorySchema
{
    public static function ingredientsReady(): bool
    {
        return Schema::hasTable('ingredients');
    }

    public static function productIngredientsReady(): bool
    {
        return Schema::hasTable('product_ingredients');
    }

    public static function movementsReady(): bool
    {
        return Schema::hasTable('inventory_movements');
    }

    public static function fullReady(): bool
    {
        return self::ingredientsReady()
            && self::productIngredientsReady()
            && self::movementsReady();
    }

    public static function hasI18nColumns(): bool
    {
        return self::ingredientsReady() && Schema::hasColumn('ingredients', 'name_en');
    }

    public static function hasLowStockThreshold(): bool
    {
        return self::ingredientsReady() && Schema::hasColumn('ingredients', 'low_stock_threshold');
    }

    public static function hasReasonColumn(): bool
    {
        return self::movementsReady() && Schema::hasColumn('inventory_movements', 'reason');
    }
}
