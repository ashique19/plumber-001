<?php

use App\Support\EnabledLocales;

it('exposes only en, bn, and ar as UI-enabled locales', function () {
    expect(EnabledLocales::codes())->toBe(['en', 'bn', 'ar']);
});

it('resolves enabled locale codes', function () {
    expect(EnabledLocales::resolve('bn'))->toBe('bn')
        ->and(EnabledLocales::resolve('ar'))->toBe('ar')
        ->and(EnabledLocales::resolve('en'))->toBe('en');
});

it('falls back to the configured default when ur or hi is requested explicitly', function () {
    expect(EnabledLocales::resolve('ur'))->toBe('bn')
        ->and(EnabledLocales::resolve('hi'))->toBe('bn');
});

it('marks arabic as rtl in locale config while urdu remains in files only', function () {
    expect(config('locales.supported.ar.dir'))->toBe('rtl')
        ->and(config('locales.supported.ur.dir'))->toBe('rtl')
        ->and(config('locales.supported.ur.enabled'))->toBeFalse()
        ->and(config('locales.supported.hi.enabled'))->toBeFalse();
});
