<?php

use App\Support\Invoicing\InvoicePrintQrCaption;

it('exposes fixed EN and AR QR caption lines for PDF print', function () {
    expect(InvoicePrintQrCaption::enLines())->toBe([
        'This QR code is encoded as per',
        'ZATCA e-invoicing requirements',
    ]);

    $arLines = InvoicePrintQrCaption::arLines();
    expect($arLines)->not->toBeEmpty()
        ->and(implode(' ', $arLines))->toContain('الإلكترونية');
    if (count($arLines) > 1) {
        $lastWords = preg_split('/\s+/u', (string) end($arLines)) ?: [];
        expect(count($lastWords))->toBeGreaterThan(1);
    }
});