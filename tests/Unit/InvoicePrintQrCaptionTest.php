<?php

use App\Support\Invoicing\InvoicePrintQrCaption;

it('exposes fixed EN and AR QR caption lines for PDF print', function () {
    expect(InvoicePrintQrCaption::enLines())->toBe([
        'This QR code is encoded as per',
        'ZATCA e-invoicing requirements',
    ])->and(InvoicePrintQrCaption::arLines())->toBe([
        'تم ترميز هذا الرمز وفقاً لمتطلبات هيئة الزكاة والضريبة والجمارك',
        'للفاتورة الإلكترونية',
    ]);
});