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

it('falls back to english when ur or hi is requested explicitly', function () {
    expect(EnabledLocales::resolve('ur'))->toBe('en')
        ->and(EnabledLocales::resolve('hi'))->toBe('en');
});

it('marks arabic as rtl in locale config while urdu remains in files only', function () {
    expect(config('locales.supported.ar.dir'))->toBe('rtl')
        ->and(config('locales.supported.ur.dir'))->toBe('rtl')
        ->and(config('locales.supported.ur.enabled'))->toBeFalse()
        ->and(config('locales.supported.hi.enabled'))->toBeFalse();
});
