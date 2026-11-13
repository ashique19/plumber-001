<?php

namespace App\Support\Themes;

use App\Models\BusinessSetting;
use Illuminate\Support\Str;

class ThemeAssetCatalog
{
    /**
     * @return list<string> Paths relative to /public (e.g. images/fancy-coffee/hero.jpg)
     */
    public static function assetPathsForTenant(BusinessSetting $tenant): array
    {
        $paths = [];
        self::walk($tenant->settings ?? [], $paths);

        $logo = data_get($tenant->settings, 'theme.logo_url');
        if (is_string($logo) && self::isBundledPath($logo)) {
            $paths[] = ltrim($logo, '/');
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $paths
     */
    private static function walk(array $data, array &$paths): void
    {
        foreach ($data as $value) {
            if (is_string($value) && self::isBundledPath($value)) {
                $paths[] = ltrim($value, '/');
            }
            if (is_array($value)) {
                self::walk($value, $paths);
            }
        }
    }

    private static function isBundledPath(string $value): bool
    {
        if (Str::startsWith($value, ['http://', 'https://', '//', 'data:'])) {
            return false;
        }

        return Str::startsWith($value, ['images/', 'theme-export/', 'storage/']);
    }
}
