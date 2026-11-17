<?php

namespace App\Support\Invoicing;

/**
 * Maps plumber buyer national-address snapshot keys to Z-Heart invoice buyer.address keys.
 */
final class ZHeartBuyerAddressMapper
{
    /**
     * @param  array<string, mixed>  $address  Plumber snapshot (buyer_national_address)
     * @return array<string, string>
     */
    public static function toZHeart(array $address, string $fallbackCountry = 'SA', bool $forceCountry = false): array
    {
        $building = trim((string) ($address['building'] ?? ''));
        $street = trim((string) ($address['street'] ?? ''));
        $district = trim((string) ($address['district'] ?? $address['subdivision'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $postalDigits = preg_replace('/\D/', '', (string) ($address['postal_code'] ?? $address['postal'] ?? $address['postalZone'] ?? '')) ?? '';
        $additional = trim((string) ($address['additional'] ?? ''));
        $country = strtoupper(trim((string) ($address['country'] ?? $fallbackCountry))) ?: 'SA';

        $out = [];

        if ($forceCountry || $country !== '') {
            $out['country'] = $country;
        }

        if ($street !== '') {
            $out['street'] = $street;
        }
        if ($city !== '') {
            $out['city'] = $city;
        }
        if ($building !== '') {
            $out['buildingNumber'] = $building;
        }
        if ($district !== '') {
            $out['subdivision'] = $district;
        }
        if ($postalDigits !== '') {
            $out['postalZone'] = $postalDigits;
            $out['postal'] = $postalDigits;
        }
        if ($additional !== '') {
            $out['additional'] = $additional;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public static function postalDigits(array $address): string
    {
        return preg_replace('/\D/', '', (string) ($address['postal_code'] ?? $address['postal'] ?? $address['postalZone'] ?? '')) ?? '';
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public static function countryCode(array $address, string $fallback = 'SA'): string
    {
        $country = strtoupper(trim((string) ($address['country'] ?? $fallback)));

        return $country !== '' ? $country : $fallback;
    }

    /**
     * B2C (simplified) may include buyer.address only when every ZATCA national-address field is present and valid.
     *
     * @param  array<string, mixed>  $address
     */
    public static function hasCompleteSaudiNationalAddress(array $address): bool
    {
        $building = trim((string) ($address['building'] ?? ''));
        $street = trim((string) ($address['street'] ?? ''));
        $district = trim((string) ($address['district'] ?? $address['subdivision'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $postal = self::postalDigits($address);

        if ($building === '' || $street === '' || $district === '' || $city === '') {
            return false;
        }

        if (! preg_match('/^\d{5}$/', $postal)) {
            return false;
        }

        $country = strtoupper(trim((string) ($address['country'] ?? 'SA')));

        return $country === '' || $country === 'SA';
    }
}