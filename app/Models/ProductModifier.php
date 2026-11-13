<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductModifier extends Model
{

    protected $fillable = [        'name_en',
        'name_ar',
        'price_ex_vat',
        'vat_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_ex_vat' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_product_modifier');
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return $locale === 'ar' && $this->name_ar ? $this->name_ar : $this->name_en;
    }
}
