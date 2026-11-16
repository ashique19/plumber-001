<?php

namespace App\Support\Customers;

final class ZatcaPartyIdScheme
{
    /** @var list<string> */
    public const SCHEMES = [
        'CRN',
        'MOM',
        'MLS',
        '700',
        'SAG',
        'NAT',
        'GCC',
        'IQA',
        'PAS',
        'OTH',
    ];

  /**
   * @return array<string, string>
   */
    public static function options(): array
    {
        $out = [];
        foreach (self::SCHEMES as $scheme) {
            $out[$scheme] = __('customers.alt_id_type.'.$scheme);
        }

        return $out;
    }

    public static function isValid(string $scheme): bool
    {
        return in_array(strtoupper($scheme), self::SCHEMES, true);
    }
}