<?php

use App\Support\VatPricing;

it('converts between ex and inc vat at 15 percent', function () {
    expect(VatPricing::incFromEx(100, 15))->toBe(115.0)
        ->and(VatPricing::exFromInc(115, 15))->toBe(100.0);
});
