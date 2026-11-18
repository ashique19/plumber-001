<?php

namespace App\Support\Invoicing;

use App\Support\BusinessProfile;

final class BuyerAddressPrintHtml
{
    /**
     * @param  array<string, mixed>  $customer
     */
    public static function fromCustomer(array $customer): string
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

            return '<div class="invoice-a4__buyer-address-cell">'
                .'<div class="invoice-a4__buyer-address-en">'
                .e($fallback)
                .'</div></div>';
        }

        $enHtml = '';
        foreach ($enLines as $line) {
            $enHtml .= '<div class="invoice-a4__buyer-address-en__line invoice-pdf-sans-value">'.e($line).'</div>';
        }

        return '<div class="invoice-a4__buyer-address-cell">'
            .'<div class="invoice-a4__buyer-address-en">'.$enHtml.'</div>'
            .'</div>';
    }
}