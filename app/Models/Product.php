<?php

namespace App\Models;

use App\Enums\ProductItemType;
use App\Enums\VatZatcaCategory;
use App\Support\Catalog\CatalogI18n;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['branch_id',
        'product_category_id',
        'product_unit_id',
        'product_size_id',
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
        'vat_zatca_category',
        'sort_order',
        'is_active',
        'show_on_homepage',
        'show_on_menu',
        'track_stock',
        'stock_quantity',
        'low_stock_threshold',
        'cost_ex_vat',
        'archived_at',
        'name_i18n_review',
    ];

    protected function casts(): array
    {
        return [
            'price_ex_vat' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
            'show_on_homepage' => 'boolean',
            'show_on_menu' => 'boolean',
            'track_stock' => 'boolean',
            'stock_quantity' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'cost_ex_vat' => 'decimal:2',
            'item_type' => ProductItemType::class,
            'archived_at' => 'datetime',
            'name_i18n_review' => 'boolean',
        ];
    }

    public function scopeVisibleInCatalog(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->when(
                CatalogUnitsSizesSchema::ready(),
                fn (Builder $q) => $q->whereNull('archived_at'),
            );
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function vatZatcaCategory(): VatZatcaCategory
    {
        $raw = strtoupper((string) ($this->vat_zatca_category ?? 'S'));

        return VatZatcaCategory::tryFrom($raw) ?? VatZatcaCategory::Standard;
    }

    public function ublUnitCode(): string
    {
        return $this->unit?->ubl_unit_code ?? 'PCE';
    }

    public function hasKnownCostExVat(): bool
    {
        return $this->cost_ex_vat !== null;
    }

    public function unitPickerLabel(?string $locale = null): string
    {
        if ($this->unit !== null) {
            return $this->unit->displayName($locale);
        }

        return $this->itemType()->label();
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

    public function productIngredients(): HasMany
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
