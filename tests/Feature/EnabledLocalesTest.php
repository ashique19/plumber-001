<?php

use App\Support\EnabledLocales;

it('exposes configured storefront locales including ur and hi metadata', function () {
    expect(EnabledLocales::codes())->toContain('en', 'ar', 'bn', 'ur', 'hi');
});

it('resolves locale codes including rtl ar and ur', function () {
    expect(EnabledLocales::resolve('ur'))->toBe('ur')
        ->and(EnabledLocales::resolve('hi'))->toBe('hi')
        ->and(EnabledLocales::resolve('ar'))->toBe('ar');
});

it('marks arabic and urdu as rtl in locale config', function () {
    expect(config('locales.supported.ar.dir'))->toBe('rtl')
        ->and(config('locales.supported.ur.dir'))->toBe('rtl');
});
