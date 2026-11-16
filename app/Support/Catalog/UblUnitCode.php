<?php

namespace App\Support\Catalog;

final class UblUnitCode
{
    /**
     * Map internal unit codes to UN/ECE Recommendation 20 codes for Z-Heart / UBL.
     */
    public static function forCatalogCode(string $code): string
    {
        return match (strtoupper(trim($code))) {
            'PCS' => 'PCE',
            'M' => 'MTR',
            'BOX' => 'BX',
            'ROLL' => 'RO',
            'KG' => 'KGM',
            'L' => 'LTR',
            'SET' => 'SET',
            'HR' => 'HUR',
            'JOB' => 'E48',
            default => 'C62',
        };
    }
}