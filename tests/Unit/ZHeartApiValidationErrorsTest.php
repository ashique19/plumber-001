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

    expect($lines)->toContain('buyer.address.district: The district field is required.')
        ->and($lines)->toContain('supply_date: Supply date is required');
});

it('dedupes generic message when formatting flash', function () {
    $flash = ZHeartApiValidationErrors::formatFlashMessage(
        'Z-Heart returned HTTP 422 — The invoice payload is invalid.',
        [
            'The invoice payload is invalid.',
            'buyer.address.district: The district field is required.',
        ],
    );

    expect(substr_count($flash, 'The invoice payload is invalid.'))->toBe(1)
        ->and($flash)->toContain('buyer.address.district');
});