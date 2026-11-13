<?php

namespace App\Support\Storefront;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Inventory\IngredientStockService;
use App\Support\Catalog\ProductStorefrontVisibility;
use App\Support\Inventory\IngredientInventorySchema;
use App\Support\PosProductImagery;
use App\Support\VatPricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class StorefrontCatalog
{
    /** @return Collection<int, Product> */
    public static function featuredProducts(): Collection
    {
        if (! ProductStorefrontVisibility::columnsExist()) {
            return collect();
        }

        return self::baseProductQuery()
            ->where('show_on_homepage', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->limit(12)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public static function menuCategoryGroups(?string $locale = null): array
    {
        if (! ProductStorefrontVisibility::columnsExist()) {
            return [];
        }

        $locale ??= app()->getLocale();
        $products = self::baseProductQuery()
            ->where('show_on_menu', true)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $grouped = $products->groupBy(fn (Product $p) => $p->product_category_id ?? 0);
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => self::applyBranchScope($q))
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->keyBy('id');

        $sections = [];
        foreach ($categories as $category) {
            $items = $grouped->get($category->id);
            if ($items === null || $items->isEmpty()) {
                continue;
            }
            $sections[] = self::mapCategorySection($category, $items, $locale);
        }

        $uncategorized = $grouped->get(0);
        if ($uncategorized !== null && $uncategorized->isNotEmpty()) {
            $sections[] = [
                'slug' => 'other',
                'name' => __('catalog.uncategorized_en'),
                'name_en' => __('catalog.uncategorized_en'),
                'name_ar' => __('catalog.uncategorized_ar'),
                'name_bn' => __('catalog.uncategorized_bn', [], 'bn'),
                'items' => $uncategorized->map(fn (Product $p) => self::mapProductItem($p, $locale))->values()->all(),
            ];
        }

        return $sections;
    }

    /** @return list<array<string, mixed>> */
    public static function cafeFeaturedCards(Collection $products, ?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return $products->map(function (Product $product) use ($locale) {
            $priceInc = VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate);

            return [
                'name' => $product->displayName($locale),
                'name_en' => $product->displayName('en'),
                'name_ar' => $product->displayName('ar'),
                'name_bn' => $product->displayName('bn'),
                'desc' => $product->displayDescription($locale),
                'price_sar' => $priceInc,
                'image' => PosProductImagery::relativePathForSku($product->sku, $product->image_url),
                'from_catalog' => true,
            ];
        })->values()->all();
    }

    /** @return Builder<Product> */
    private static function baseProductQuery(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => self::applyBranchScope($q));
    }

    private static function applyBranchScope(Builder $query): void
    {
        $activeBranchIds = Branch::query()->where('is_active', true)->pluck('id');
        $query->where(function (Builder $inner) use ($activeBranchIds) {
            $inner->whereNull('branch_id');
            if ($activeBranchIds->isNotEmpty()) {
                $inner->orWhereIn('branch_id', $activeBranchIds);
            }
        });
    }

    /** @param Collection<int, Product> $items */
    private static function mapCategorySection(ProductCategory $category, Collection $items, string $locale): array
    {
        return [
            'slug' => Str::slug($category->name_en) ?: 'cat-'.$category->id,
            'name' => $category->displayName($locale),
            'items' => $items->map(fn (Product $p) => self::mapProductItem($p, $locale))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private static function mapProductItem(Product $product, string $locale): array
    {
        $priceInc = VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate);
        $soldOut = false;
        if (IngredientInventorySchema::ingredientsReady()) {
            $branch = Branch::query()->where('is_active', true)->orderBy('id')->first();
            $soldOut = app(IngredientStockService::class)->availabilityForProduct($product, $branch, 1.0) === 'out';
        }

        return [
            'sold_out' => $soldOut,
            'slug' => $product->sku ?: 'product-'.$product->id,
            'name' => $product->displayName($locale),
            'price_sar' => $priceInc,
        ];
    }
}
