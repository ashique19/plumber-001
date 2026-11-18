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
            $enHtml .= '<div class="invoice-a4__buyer-address-en__line">'.e($line).'</div>';
        }

        /** @var list<string>|null $arLines */
        $arLines = $customer['address_ar_line_html'] ?? null;
        if (! is_array($arLines) || $arLines === []) {
            $arLines = $na !== [] ? BusinessProfile::formatNationalAddressArPdfLineHtml($na) : [];
        }

        $arHtml = '';
        foreach ($arLines as $line) {
            $arHtml .= '<div class="invoice-a4__buyer-address-ar__line invoice-pdf-ar-block invoice-pdf-ar-address-line" dir="rtl" style="text-align:right">'
                .$line
                .'</div>';
        }

        $html = '<div class="invoice-a4__buyer-address-cell">';
        if ($enHtml !== '') {
            $html .= '<div class="invoice-a4__buyer-address-en">'.$enHtml.'</div>';
        }
        if ($arHtml !== '') {
            $html .= '<div class="invoice-a4__buyer-address-ar invoice-print-ar" dir="rtl">'.$arHtml.'</div>';
        }
        $html .= '</div>';

        return $html;
    }
}