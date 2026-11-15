<?php

namespace App\Support\Admin;

final class AdminLocaleFont
{
    public static function preloadPath(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return match ($locale) {
            'bn' => '/fonts/admin/bn/noto-sans-bengali-400.woff2',
            'ar' => '/fonts/admin/ar/ibm-plex-sans-arabic-400.woff2',
            default => '/fonts/admin/en/plus-jakarta-sans-400.woff2',
        };
    }
}