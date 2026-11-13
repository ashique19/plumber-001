<?php

namespace App\Support;

use App\Support\Storefront\PlumberStorefrontImagery;

/**
 * Maps POS SKUs to public plumber imagery paths.
 */
final class PosProductImagery
{
    /** @var array<string, string> */
    private const CATEGORY_ICONS = [
        'emergency' => 'wrench',
        'maintenance' => 'wrench',
        'installation' => 'wrench',
        'parts' => 'wrench',
        'hot' => 'fire',
        'cold' => 'snow',
        'drinks' => 'cup',
    ];

    public static function relativePathForSku(?string $sku, ?string $stored = null): ?string
    {
        if (is_string($stored) && $stored !== '' && is_file(public_path($stored))) {
            return $stored;
        }

        if ($sku === null || $sku === '') {
            return null;
        }

        return PlumberStorefrontImagery::pathForSku($sku);
    }

    public static function imageForSku(?string $sku, ?string $stored = null): ?string
    {
        $relative = self::relativePathForSku($sku, $stored);

        return $relative !== null ? asset($relative) : null;
    }

    public static function assetFromRelative(?string $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        return asset($path);
    }

    public static function iconForCategory(?string $iconKey, string $nameEn): string
    {
        if ($iconKey !== null && $iconKey !== '') {
            return $iconKey;
        }

        $slug = strtolower($nameEn);
        foreach (self::CATEGORY_ICONS as $needle => $icon) {
            if (str_contains($slug, $needle)) {
                return $icon;
            }
        }

        return 'wrench';
    }
}
