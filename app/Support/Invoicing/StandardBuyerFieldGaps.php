<?php

namespace App\Support\Invoicing;

final class StandardBuyerFieldGaps
{
    /**
     * @param  object  $host
     * @return list<string>
     */
    public static function labelsFor(object $host): array
    {
        $gaps = [];

        if (self::missingBuyerIdentity($host)) {
            $gaps[] = __('invoices.gap_buyer_vat_number');
        }

        if (trim((string) ($host->addrBuilding ?? '')) === '') {
            $gaps[] = __('business.fields.building');
        }
        if (trim((string) ($host->addrStreet ?? '')) === '') {
            $gaps[] = __('business.fields.street');
        }
        if (trim((string) ($host->addrCity ?? '')) === '') {
            $gaps[] = __('business.fields.city');
        }
        if (trim((string) ($host->addrDistrict ?? '')) === '') {
            $gaps[] = __('business.fields.district');
        }
        if (trim((string) ($host->addrPostal ?? '')) === '') {
            $gaps[] = __('business.fields.postal');
        }

        return $gaps;
    }

    /**
     * @param  object  $host
     */
    public static function hasGaps(object $host): bool
    {
        return self::missingKeys($host) !== [];
    }

    /**
     * @param  object  $host
     * @return list<string>
     */
    public static function missingKeys(object $host): array
    {
        $missing = [];

        if (self::missingBuyerIdentity($host)) {
            $missing[] = 'buyer_identity';
        }

        if (trim((string) ($host->addrBuilding ?? '')) === '') {
            $missing[] = 'addrBuilding';
        }
        if (trim((string) ($host->addrStreet ?? '')) === '') {
            $missing[] = 'addrStreet';
        }
        if (trim((string) ($host->addrCity ?? '')) === '') {
            $missing[] = 'addrCity';
        }
        if (trim((string) ($host->addrDistrict ?? '')) === '') {
            $missing[] = 'addrDistrict';
        }
        if (trim((string) ($host->addrPostal ?? '')) === '') {
            $missing[] = 'addrPostal';
        }

        $country = trim((string) ($host->buyerCountry ?? ''));
        if ($country === '' || strlen($country) !== 2) {
            $missing[] = 'buyerCountry';
        }

        return $missing;
    }

    private static function missingBuyerIdentity(object $host): bool
    {
        if (! self::buyerVatRegistered($host)) {
            return false;
        }

        $vat = preg_replace('/\D/', '', (string) ($host->buyerVat ?? '')) ?? '';

        return $vat === '';
    }

    private static function buyerVatRegistered(object $host): bool
    {
        if (property_exists($host, 'buyerVatRegistered')) {
            return filter_var($host->buyerVatRegistered, FILTER_VALIDATE_BOOLEAN);
        }

        return filled($host->buyerVat ?? null);
    }
}