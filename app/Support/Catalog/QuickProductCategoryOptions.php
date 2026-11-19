<?php

namespace App\Support\Catalog;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

final class QuickProductCategoryOptions
{
    /**
     * @return \Illuminate\Support\Collection<int, ProductCategory>
     */
    public static function ordered(): Collection
    {
        $nameCol = ProductCategorySchema::nameOrderColumn();

        return ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy($nameCol)
            ->get();
    }

    public static function defaultId(): ?int
    {
        return self::ordered()->first()?->id;
    }
}