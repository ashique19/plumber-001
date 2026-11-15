<?php

namespace App\Support\Invoicing;

/**
 * ZATCA e-invoicing phase-1 QR payload (TLV, base64) for unscanned local issue.
 */
final class ZatcaPhase1QrEncoder
{
    public static function encodeBase64(
        string $sellerName,
        string $vatNumber,
        string $timestampIso8601,
        string $invoiceTotalIncVat,
        string $vatTotal,
    ): string {
        $binary = self::tlv(1, $sellerName)
            .self::tlv(2, self::digitsOnly($vatNumber))
            .self::tlv(3, $timestampIso8601)
            .self::tlv(4, $invoiceTotalIncVat)
            .self::tlv(5, $vatTotal);

        return base64_encode($binary);
    }

    private static function tlv(int $tag, string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        if ($length > 255) {
            $bytes = substr($bytes, 0, 255);
            $length = 255;
        }

        return chr($tag).chr($length).$bytes;
    }

    private static function digitsOnly(string $vat): string
    {
        return preg_replace('/\D/', '', $vat) ?? '';
    }
}