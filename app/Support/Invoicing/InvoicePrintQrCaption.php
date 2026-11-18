<?php

namespace App\Support\Invoicing;

/**
 * Pre-broken QR captions for modern invoice print (mPDF-safe Arabic lines).
 */
final class InvoicePrintQrCaption
{
    /**
     * @return list<string>
     */
    public static function enLines(): array
    {
        return [
            'This QR code is encoded as per',
            'ZATCA e-invoicing requirements',
        ];
    }

    /**
     * @return list<string>
     */
    public static function arLines(): array
    {
        return [
            'تم ترميز هذا الرمز وفقاً لمتطلبات',
            'هيئة الزكاة والضريبة والجمارك للفاتورة الإلكترونية',
        ];
    }
}