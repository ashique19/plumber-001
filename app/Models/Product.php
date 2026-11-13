<?php

namespace App\Models;

use App\Enums\ProductItemType;
use App\Support\Catalog\CatalogI18n;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{

    protected $fillable = [        'branch_id',
        'product_category_id',
        'sku',
        'item_type',
        'name_en',
        'name_ar',
        'name_bn',
        'description_en',
        'description_ar',
        'description_bn',
        'image_url',
        'price_ex_vat',
        'vat_rate',
        'sort_order',
        'is_active',
        'show_on_homepage',
        'show_on_menu',
    ];

    protected function casts(): array
    {
        return [
            'price_ex_vat' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'show_on_menu' => 'boolean',
            'item_type' => ProductItemType::class,
        ];
    }

    public function itemType(): ProductItemType
    {
        return $this->item_type ?? ProductItemType::Item;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(ProductModifier::class, 'product_product_modifier');
    }

    public function productIngredients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductIngredient::class);
    }

    public function displayName(?string $locale = null): string
    {
        return CatalogI18n::localizedName($this, $locale);
    }

    public function displayDescription(?string $locale = null): string
    {
        return CatalogI18n::localizedDescription($this, $locale);
    }
}
