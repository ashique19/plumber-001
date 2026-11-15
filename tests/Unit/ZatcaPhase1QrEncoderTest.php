<?php

use App\Support\Invoicing\ZatcaPhase1QrEncoder;

it('encodes phase-1 tlv as base64', function () {
    $encoded = ZatcaPhase1QrEncoder::encodeBase64(
        sellerName: 'Demo LLC',
        vatNumber: '300000000000003',
        timestampIso8601: '2026-11-15T12:00:00+03:00',
        invoiceTotalIncVat: '207.00',
        vatTotal: '27.00',
    );

    expect($encoded)->not->toBeEmpty()
        ->and(base64_encode(base64_decode($encoded, true)))->toBe($encoded);
});