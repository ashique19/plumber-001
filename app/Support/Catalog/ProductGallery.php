<?php

namespace App\Support\Catalog;

use App\Models\Product;

final class ProductGallery
{
    public static function initials(Product $product, ?string $locale = null): string
    {
        $name = trim($product->displayName($locale));
        if ($name === '') {
            return '—';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $letters = collect($parts)
            ->filter()
            ->take(2)
            ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)));

        $initials = $letters->implode('');

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($name, 0, 1));
    }
}