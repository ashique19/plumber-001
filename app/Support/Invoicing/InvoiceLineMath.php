<?php

namespace App\Support\Invoicing;

final class InvoiceLineMath
{
    public const QTY_DECIMALS = 3;

    public static function normalizeQuantity(float $quantity): float
    {
        return round(max(0, $quantity), self::QTY_DECIMALS);
    }

    public static function lineNetExVat(float $unitExVat, float $quantity, float $lineDiscountExVat = 0.0): float
    {
        $quantity = self::normalizeQuantity($quantity);
        $grossEx = round($unitExVat * $quantity, 2);

        return round(max(0, $grossEx - round($lineDiscountExVat, 2)), 2);
    }

    public static function lineVatAmount(float $netExVat, float $vatRate): float
    {
        $rate = max(0.0, $vatRate);

        return round($netExVat * $rate / 100, 2);
    }

    public static function lineIncVat(float $netExVat, float $vatRate): float
    {
        return round($netExVat + self::lineVatAmount($netExVat, $vatRate), 2);
    }

    public static function formatQuantity(float $quantity): string
    {
        $normalized = self::normalizeQuantity($quantity);
        $formatted = number_format($normalized, self::QTY_DECIMALS, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') !== ''
            ? rtrim(rtrim($formatted, '0'), '.')
            : '0';
    }
}