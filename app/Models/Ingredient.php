<?php

namespace App\Models;

use App\Support\Catalog\CatalogLocalizedText;
use App\Support\Inventory\IngredientInventorySchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{

    protected $fillable = [
        'branch_id',
        'name_en',
        'name_ar',
        'name_bn',
        'unit',
        'quantity_on_hand',
        'unit_cost_ex_vat',
        'reorder_level',
        'low_stock_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'unit_cost_ex_vat' => 'decimal:4',
            'reorder_level' => 'decimal:3',
            'low_stock_threshold' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (IngredientInventorySchema::hasI18nColumns()) {
            return CatalogLocalizedText::pick(
                (string) $this->name_en,
                (string) ($this->name_ar ?? ''),
                (string) ($this->name_bn ?? ''),
                $locale,
            );
        }

        if (IngredientInventorySchema::hasLegacyNameColumn()) {
            return (string) $this->getAttribute('name');
        }

        return (string) ($this->name_en ?? '');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return IngredientInventorySchema::applyDisplayNameOrder($query);
    }

    public function unitCostExVat(): ?float
    {
        if (! IngredientInventorySchema::hasUnitCost()) {
            return null;
        }

        $value = $this->getAttribute('unit_cost_ex_vat');
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    public function isLowStock(): bool
    {
        $threshold = IngredientInventorySchema::hasLowStockThreshold()
            ? (float) ($this->low_stock_threshold ?? 0)
            : (float) ($this->reorder_level ?? 0);

        if ($threshold <= 0) {
            return false;
        }

        return (float) $this->quantity_on_hand <= $threshold;
    }
}
