<?php

namespace App\Support\Storefront;

final class CafeMapsUrl
{
    /**
     * @param  array<string, mixed>  $branch
     */
    public static function branchDirections(array $branch, string $fallback): string
    {
        $lat = $branch['lat'] ?? null;
        $lng = $branch['lng'] ?? null;
        if (! filled($lat) || ! filled($lng)) {
            return $fallback;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($lat.','.$lng);
    }
}
