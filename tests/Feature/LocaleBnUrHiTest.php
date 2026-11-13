<?php

use App\Support\EnabledLocales;
use Database\Seeders\OwnerAdminSeeder;

it('keeps urdu translation files on disk but hides ur from the enabled locale list', function () {
    expect(is_file(lang_path('ur/invoicing.php')))->toBeTrue()
        ->and(__('plumber.nav_services', [], 'ur'))->not->toBe('plumber.nav_services')
        ->and(EnabledLocales::isEnabled('ur'))->toBeFalse()
        ->and(EnabledLocales::isEnabled('hi'))->toBeFalse();
});

it('enables bengali in the enabled locale list with plumber translations', function () {
    expect(EnabledLocales::isEnabled('bn'))->toBeTrue()
        ->and(is_file(lang_path('bn/plumber.php')))->toBeTrue();
});

it('rejects disabled locales on the login page query string', function () {
    $this->withoutVite();
    (new OwnerAdminSeeder)->run();

    $response = $this->get('/?locale=ur');
    $response->assertOk();
    expect(session('locale'))->toBe('en');

    $this->get('/?locale=hi')->assertOk();
    expect(session('locale'))->toBe('en');
});
