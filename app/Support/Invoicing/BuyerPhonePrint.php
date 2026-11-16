<?php

namespace App\Support\Invoicing;

final class BuyerPhonePrint
{
    public static function format(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if ($digits === '') {
            return $raw;
        }

        if (str_starts_with($digits, '966')) {
            $national = substr($digits, 3);

            return '+966 '.ltrim($national, '0');
        }

        if (str_starts_with($digits, '0')) {
            return '+966 '.substr($digits, 1);
        }

        if (strlen($digits) === 9 && $digits[0] === '5') {
            return '+966 '.$digits;
        }

        return str_starts_with($raw, '+') ? $raw : '+'.$digits;
    }
}