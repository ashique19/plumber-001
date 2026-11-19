<?php

namespace App\Support\Catalog;

final class CategoryIconOptions
{
    public const DEFAULT = 'cup';

    /**
     * Icons that render in {@see resources/views/components/app/icon.blade.php}.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            'cup',
            'wrench',
            'package',
            'shield',
            'alert',
            'fire',
            'snow',
            'building',
            'cube',
            'clipboard',
            'cart',
            'home',
            'users',
            'phone',
            'map-pin',
            'banknotes',
            'chart',
            'document',
        ];
    }

    public static function isSupported(?string $key): bool
    {
        $key = trim((string) $key);

        return $key !== '' && in_array($key, self::keys(), true);
    }

    public static function normalize(?string $key): string
    {
        return self::isSupported($key) ? trim((string) $key) : self::DEFAULT;
    }

    public static function label(string $key): string
    {
        return __('catalog.category_icons.'.self::normalize($key));
    }
}