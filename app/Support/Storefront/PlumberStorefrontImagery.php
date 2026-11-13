<?php

namespace App\Support\Storefront;

final class PlumberStorefrontImagery
{
    private const SLUG_TO_FILE = [
        'leak-repair' => 'leak-repair.webp',
        'burst-pipe' => 'burst-pipe.webp',
        'drain-clear' => 'drain-clear.webp',
        'fixture-service' => 'fixture-service.webp',
        'water-heater' => 'water-heater.webp',
        'filtration' => 'filtration.webp',
    ];

    /** @var array<string, string> */
    private const SLUG_IMAGE_FALLBACK = [
        'leak-detection' => 'leak-repair',
        'water-tank' => 'water-heater',
        'water-pump' => 'burst-pipe',
        'ppr-piping' => 'burst-pipe',
        'sanitary-fixtures' => 'fixture-service',
        'water-heater-maint' => 'water-heater',
        'sewer-jetting' => 'drain-clear',
        'preventive-maintenance' => 'fixture-service',
    ];

    private const SKU_TO_SLUG = [
        'SRV-LEAK' => 'leak-repair',
        'SRV-BURST' => 'burst-pipe',
        'SRV-DRAIN' => 'drain-clear',
        'SRV-FIX' => 'fixture-service',
        'SRV-HEAT' => 'water-heater',
        'SRV-FILT' => 'filtration',
    ];

    public static function heroPath(): string
    {
        return 'images/plumber/hero.webp';
    }

    public static function heroUrl(): string
    {
        return asset(self::heroPath());
    }

    public static function pathForSlug(string $slug): ?string
    {
        $resolved = self::SLUG_IMAGE_FALLBACK[$slug] ?? $slug;
        $file = self::SLUG_TO_FILE[$resolved] ?? null;

        return $file !== null ? 'images/plumber/'.$file : null;
    }

    public static function urlForSlug(string $slug): ?string
    {
        $path = self::pathForSlug($slug);

        return $path !== null ? asset($path) : null;
    }

    public static function pathForSku(string $sku): ?string
    {
        $slug = self::SKU_TO_SLUG[$sku] ?? null;

        return $slug !== null ? self::pathForSlug($slug) : null;
    }

    public static function serviceImageAlt(string $serviceName, string $companyName): string
    {
        return __('plumber.service_image_alt', [
            'service' => $serviceName,
            'company' => $companyName,
        ]);
    }
}
