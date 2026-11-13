<?php

namespace App\Support\Geo;

final class GeoMapsUrl
{
    public static function googleQuery(?string $lat, ?string $lng): string
    {
        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return '';
        }
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return '';
        }

        $latR = round((float) $lat, 6);
        $lngR = round((float) $lng, 6);

        return sprintf('https://www.google.com/maps?q=%s,%s', $latR, $lngR);
    }
}
