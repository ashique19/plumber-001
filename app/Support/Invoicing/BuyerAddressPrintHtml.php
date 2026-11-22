<?php

namespace App\Support\Invoicing;

use App\Support\BusinessProfile;

final class BuyerAddressPrintHtml
{
    /**
     * @param  array<string, mixed>  $customer
     */
    public static function fromCustomer(array $customer, bool $forPdf = false): string
    {
        $na = (array) ($customer['address_parts'] ?? []);

        /** @var list<string>|null $enLines */
        $enLines = $customer['address_en_lines'] ?? null;
        if (! is_array($enLines) || $enLines === []) {
            $enLines = $na !== [] ? BusinessProfile::formatNationalAddressEnPrintLines($na) : [];
        }

        if ($enLines === []) {
            $fallback = trim((string) ($customer['address_print'] ?? $customer['address'] ?? ''));
            if ($fallback === '') {
                return '';
            }
            $enLines = preg_split('/\r\n|\n|\r/', $fallback) ?: [];
        }

        $places = [
            (string) ($na['district'] ?? ''),
            (string) ($na['city'] ?? $customer['city'] ?? ''),
        ];
        $enLines = BusinessProfile::normalizePrintAddressLines($enLines, $places);
        if ($enLines === []) {
            return '';
        }

        $enHtml = '';
        foreach ($enLines as $line) {
            $enHtml .= '<div class="invoice-a4__buyer-address-en__line invoice-pdf-sans-value">'
                .self::encodeLine($line, $forPdf)
                .'</div>';
        }

        return '<div class="invoice-a4__buyer-address-cell">'
            .'<div class="invoice-a4__buyer-address-en">'.$enHtml.'</div>'
            .'</div>';
    }

    private static function encodeLine(string $line, bool $forPdf): string
    {
        if (! $forPdf) {
            return e($line);
        }

        $parts = preg_split('/\s*,\s*/u', trim($line)) ?: [];
        $encoded = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $encoded[] = '<span class="invoice-pdf-address-part">'.e($part).'</span>';
        }

        if ($encoded === []) {
            return e($line);
        }

        return implode(', ', $encoded);
    }
}