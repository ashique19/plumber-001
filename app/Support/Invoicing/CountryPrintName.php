<?php

namespace App\Support\Invoicing;

final class CountryPrintName
{
    public static function english(?string $iso): ?string
    {
        $iso = strtoupper(trim((string) $iso));

        return match ($iso) {
            'SA' => 'Kingdom of Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'BH' => 'Bahrain',
            'KW' => 'Kuwait',
            'OM' => 'Oman',
            'QA' => 'Qatar',
            default => $iso !== '' ? $iso : null,
        };
    }
}