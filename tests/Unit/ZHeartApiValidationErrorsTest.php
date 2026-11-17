<?php

use App\Support\Invoicing\ZHeartApiValidationErrors;

it('flattens z-heart errors map into field lines', function () {
    $lines = ZHeartApiValidationErrors::collect([
        'message' => 'The invoice payload is invalid.',
        'errors' => [
            'buyer.address.district' => ['The district field is required.'],
            'supply_date' => 'Supply date is required',
        ],
    ]);

    expect($lines)->toContain(__('invoices.zatca_precheck.buyer_district'))
        ->and($lines)->toContain(__('invoices.zatca_precheck.supply_date'));
});

it('dedupes generic message when formatting flash', function () {
    $flash = ZHeartApiValidationErrors::formatFlashMessage(
        'Z-Heart returned HTTP 422 — The invoice payload is invalid.',
        [
            'The invoice payload is invalid.',
            'buyer.address.district: The district field is required.',
            'messages: buyer.address.postal is required',
        ],
    );

    expect(substr_count($flash, 'The invoice payload is invalid.'))->toBe(1)
        ->and($flash)->toContain(__('invoices.zatca_precheck.buyer_district'))
        ->and($flash)->toContain(__('invoices.zatca_precheck.buyer_postal'))
        ->and(substr_count($flash, __('invoices.zatca_precheck.buyer_district')))->toBe(1);
});