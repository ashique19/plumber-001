<?php

namespace App\Support\Invoicing;

final class SellerHeaderIds
{
    /**
     * @param  array<string, mixed>  $t
     * @return list<array{label: string, value: string, value_html: string}>
     */
    public static function linesForSide(array $t, string $side): array
    {
        $lines = [];

        if (filled($t['trn'] ?? null)) {
            $value = (string) $t['trn'];
            $lines[] = [
                'label' => $side === 'ar' ? 'الرقم الضريبي' : 'VAT No.',
                'value' => $value,
                'value_html' => self::ltrDigitsHtml($value),
            ];
        }

        if (filled($t['cr_number'] ?? null)) {
            $value = (string) $t['cr_number'];
            $lines[] = [
                'label' => $side === 'ar' ? 'السجل التجاري' : 'CR No.',
                'value' => $value,
                'value_html' => self::ltrDigitsHtml($value),
            ];
        }

        return $lines;
    }

    public static function ltrDigitsHtml(string $value): string
    {
        $escaped = e($value);

        return preg_replace_callback(
            '/(\d+)/u',
            static fn (array $m): string => '<span dir="ltr" class="invoice-print-ltr-num invoice-pdf-ltr-digits">'.$m[1].'</span>',
            $escaped,
        ) ?? $escaped;
    }
}