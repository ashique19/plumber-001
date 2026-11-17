<?php

use App\Support\Invoicing\ZHeartZatcaPayloadNormalizer;

it('maps z-heart clear body clearanceStatus CLEARED to plumber cleared', function () {
    $normalized = ZHeartZatcaPayloadNormalizer::normalize([
        'signed_xml' => base64_encode('<Invoice/>'),
        'cleared_xml' => base64_encode('<Invoice cleared="1"/>'),
        'zatca' => [
            'ok' => true,
            'http_status' => 200,
            'body' => [
                'clearanceStatus' => 'CLEARED',
                'validationResults' => ['status' => 'PASS'],
            ],
        ],
    ]);

    expect($normalized['reporting_status'])->toBe('cleared')
        ->and($normalized['delivery'])->toBe('sync')
        ->and($normalized['zatca_http_status'])->toBe(200)
        ->and($normalized['remote_submission']['clearance_status'])->toBe('CLEARED');
});

it('maps pending clearance without cleared xml as delivery pending', function () {
    $normalized = ZHeartZatcaPayloadNormalizer::normalize([
        'signed_xml' => base64_encode('<Invoice/>'),
        'zatca' => [
            'ok' => true,
            'http_status' => 200,
            'body' => [
                'clearanceStatus' => 'NOT_CLEARED',
            ],
        ],
    ]);

    expect($normalized['reporting_status'])->toBe('pending')
        ->and($normalized['delivery'])->toBe('pending');
});

it('maps zatca ok false and validation errors as rejected', function () {
    $normalized = ZHeartZatcaPayloadNormalizer::normalize([
        'zatca' => [
            'ok' => false,
            'http_status' => 400,
            'body' => [
                'clearanceStatus' => 'REJECTED',
                'validationResults' => [
                    'status' => 'ERROR',
                    'errorMessages' => [['message' => 'Invalid buyer VAT number']],
                ],
            ],
        ],
    ]);

    expect($normalized['delivery'])->toBe('rejected')
        ->and($normalized['remote_submission']['errors'])->toContain('Invalid buyer VAT number');
});