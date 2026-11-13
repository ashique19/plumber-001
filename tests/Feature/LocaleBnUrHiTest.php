<?php

use App\Support\EnabledLocales;

it('keeps urdu translation files while ui lists only enabled locales', function () {
    expect(config('locales.supported.ur.enabled'))->toBeTrue()
        ->and(config('locales.supported.ur.dir'))->toBe('rtl')
        ->and(__('plumber.nav_services', [], 'ur'))->not->toBe('plumber.nav_services')
        ->and(EnabledLocales::isEnabled('ur'))->toBeTrue();
});

it('enables bengali in the enabled locale list with plumber translations', function () {
    expect(EnabledLocales::isEnabled('bn'))->toBeTrue()
        ->and(is_file(lang_path('bn/plumber.php')))->toBeTrue();
});
