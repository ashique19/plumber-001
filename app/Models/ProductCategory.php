<?php

namespace App\Models;

use App\Support\Catalog\CatalogI18n;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{

    protected $fillable = [        'branch_id',
        'name_en',
        'name_ar',
        'name_bn',
        'description_en',
        'description_ar',
        'description_bn',
        'icon_key',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
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
