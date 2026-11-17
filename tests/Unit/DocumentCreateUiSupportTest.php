<?php

use App\Support\Invoicing\DocumentCreateUiSupport;

it('normalizes decimal product vat to select option value', function () {
    expect(DocumentCreateUiSupport::normalizeLineVatRate('15.00', 15))->toBe('15')
        ->and(DocumentCreateUiSupport::normalizeLineVatRate(null, 15.0))->toBe('15')
        ->and(DocumentCreateUiSupport::normalizeLineVatRate('', 5.0))->toBe('5');
});

it('uses default 15 percent when line and product vat are empty', function () {
    expect(DocumentCreateUiSupport::normalizeLineVatRate('', null))->toBe('15');
});