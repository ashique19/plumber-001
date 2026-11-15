<?php

namespace App\Support\Inventory;

use Illuminate\Support\Facades\Schema;

final class ProductStockSchema
{
    public static function ready(): bool
    {
        return Schema::hasTable('product_stock_movements')
            && Schema::hasColumn('products', 'track_stock');
    }
}
