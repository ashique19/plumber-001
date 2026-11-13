<?php

namespace App\Support\Geo;

/**
 * Best-effort extraction of latitude/longitude from common Google Maps share URLs.
 */
class GoogleMapsCoordinateParser
{
    /**
     * @return array{lat: float, lng: float}|null
     */
    public static function parse(?string $url): ?array
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $text = urldecode(trim($url));

        if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)(?:,|\b)/', $text, $m)) {
            return self::pair((float) $m[1], (float) $m[2]);
        }

        if (preg_match('/[?&](?:q|query|ll)=(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $text, $m)) {
            return self::pair((float) $m[1], (float) $m[2]);
        }

        if (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $text, $m)) {
            return self::pair((float) $m[1], (float) $m[2]);
        }

        if (preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $text, $m)) {
            return self::pair((float) $m[1], (float) $m[2]);
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private static function pair(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }
}
