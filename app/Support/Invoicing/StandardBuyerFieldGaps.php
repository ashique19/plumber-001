<?php

namespace App\Support\Invoicing;

final class StandardBuyerFieldGaps
{
    /**
     * @param  object{
     *     buyerVat?: string,
     *     buyerCr?: string,
     *     addrBuilding?: string,
     *     addrStreet?: string,
     *     addrCity?: string,
     *     addrPostal?: string,
     * }  $host
     * @return list<string>
     */
    public static function labelsFor(object $host): array
    {
        $gaps = [];

        if (trim((string) ($host->buyerVat ?? '')) === '' && trim((string) ($host->buyerCr ?? '')) === '') {
            $gaps[] = __('invoices.gap_buyer_vat_or_cr');
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
        if (trim((string) ($host->addrPostal ?? '')) === '') {
            $gaps[] = __('business.fields.postal');
        }

        return $gaps;
    }

    /**
     * @param  object{
     *     buyerVat?: string,
     *     buyerCr?: string,
     *     addrBuilding?: string,
     *     addrStreet?: string,
     *     addrCity?: string,
     *     addrPostal?: string,
     * }  $host
     */
    public static function hasGaps(object $host): bool
    {
        return self::missingKeys($host) !== [];
    }

    /**
     * @param  object{
     *     buyerVat?: string,
     *     buyerCr?: string,
     *     buyerCountry?: string,
     *     addrBuilding?: string,
     *     addrStreet?: string,
     *     addrDistrict?: string,
     *     addrCity?: string,
     *     addrPostal?: string,
     * }  $host
     * @return list<string>
     */
    public static function missingKeys(object $host): array
    {
        $missing = [];

        if (trim((string) ($host->buyerVat ?? '')) === '' && trim((string) ($host->buyerCr ?? '')) === '') {
            $missing[] = 'vat_or_cr';
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
        if (trim((string) ($host->addrPostal ?? '')) === '') {
            $missing[] = 'addrPostal';
        }

        $country = trim((string) ($host->buyerCountry ?? ''));
        if ($country === '' || strlen($country) !== 2) {
            $missing[] = 'buyerCountry';
        }

        return $missing;
    }
}