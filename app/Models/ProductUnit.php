<?php

namespace App\Models;

use App\Support\Catalog\CatalogLocalizedText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnit extends Model
{
    protected $fillable = [
        'name_en',
        'name_ar',
        'name_bn',
        'code',
        'ubl_unit_code',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return CatalogLocalizedText::pick(
            (string) $this->name_en,
            (string) $this->name_ar,
            (string) $this->name_bn,
            $locale,
        );
    }
}