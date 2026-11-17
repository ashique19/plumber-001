<?php

use App\Support\Geo\SaudiCities;
use App\Support\Geo\SaudiNationalDefaults;

it('resolves listed cities to canonical english names', function () {
    expect(SaudiCities::resolveStored('Riyadh'))
        ->toMatchArray(['mode' => 'listed', 'key' => 'riyadh', 'english' => 'Riyadh'])
        ->and(SaudiCities::resolveStored('الرياض')['english'])->toBe('Riyadh')
        ->and(SaudiCities::normalizeForStorage('Jeddah'))->toBe('Jeddah')
        ->and(SaudiCities::normalizeForStorage('Hofuf'))->toBe('Al Ahsa');
});

it('treats unknown cities as other and stores english verbatim', function () {
    $resolved = SaudiCities::resolveStored('Small Village');
    expect($resolved['mode'])->toBe('other')
        ->and($resolved['english'])->toBe('Small Village')
        ->and(SaudiCities::normalizeForStorage('Small Village'))->toBe('Small Village');
});

it('always normalizes country to SA', function () {
    expect(SaudiNationalDefaults::normalizeCountry('US'))->toBe('SA')
        ->and(SaudiNationalDefaults::normalizeCountry(null))->toBe('SA')
        ->and(SaudiNationalDefaults::countryCode())->toBe('SA');
});

it('exposes combobox options for every configured key', function () {
    $keys = SaudiCities::keys();
    expect($keys)->not->toBeEmpty();

    $optionKeys = array_column(SaudiCities::comboboxOptions('en'), 'key');
    foreach ($keys as $key) {
        expect($optionKeys)->toContain($key);
    }
    expect($optionKeys)->toContain(SaudiCities::OTHER_KEY);
});