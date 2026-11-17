<?php

namespace App\Support\Geo;

final class SaudiNationalDefaults
{
    public const COUNTRY_CODE = 'SA';

    public static function countryCode(): string
    {
        return self::COUNTRY_CODE;
    }

    public static function countryDisplayLabel(?string $locale = null): string
    {
        $name = (string) __('customers.country.'.self::COUNTRY_CODE, [], $locale ?? app()->getLocale());

        return self::COUNTRY_CODE.' — '.$name;
    }

    public static function normalizeCountry(?string $code): string
    {
        return self::COUNTRY_CODE;
    }
}