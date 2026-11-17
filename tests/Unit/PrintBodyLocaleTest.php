<?php

use App\Support\Invoicing\PrintBodyLocale;

it('pins print bodies to English or Arabic only', function () {
    expect(PrintBodyLocale::resolveFromRequest('bn'))->toBe('en')
        ->and(PrintBodyLocale::resolveFromRequest('en'))->toBe('en')
        ->and(PrintBodyLocale::resolveFromRequest('ar'))->toBe('ar')
        ->and(PrintBodyLocale::resolveFromRequest(null))->toBe('en');
});