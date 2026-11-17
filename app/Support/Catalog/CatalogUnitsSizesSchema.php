<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Schema;

final class CatalogUnitsSizesSchema
{
    public static function ready(): bool
    {
        return Schema::hasTable('product_units')
            && Schema::hasTable('product_sizes');
    }
}