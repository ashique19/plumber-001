<?php

namespace App\Services\Pos;

use App\Enums\ProductItemType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;

class PosCatalogQuery
{
    /**
     * @return Collection<int, ProductCategory>
     */
    public function categoriesForBranch(Branch $branch): Collection
    {
        return ProductCategory::query()
            ->where('is_active', true)
            ->where(function ($q) use ($branch) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function productsForBranch(?int $categoryId, Branch $branch, ?ProductItemType $itemType = null): Collection
    {
        return Product::query()
            ->with('modifiers')
            ->where('is_active', true)
            ->when($itemType !== null, fn ($q) => $q->where('item_type', $itemType->value))
            ->when($categoryId, fn ($q) => $q->where('product_category_id', $categoryId))
            ->where(function ($q) use ($branch) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();
    }
}
