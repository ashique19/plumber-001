<?php

use App\Models\BusinessSetting;
use App\Support\Invoicing\ZHeartCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

it('prefers stored base url and encrypted api key over env', function () {
    config([
        'zheart.base_url' => 'https://env.example.test',
        'zheart.api_key' => 'zh_env1234.ABCDEFGHIJKLMNOP',
    ]);

    $plain = 'zh_stored1234.ZZZZZZZZZZZZZZZZ';
    $settings = BusinessSetting::defaultSettings();
    $settings = ZHeartCredentials::applyKeyToSettings($settings, $plain);
    $settings['zheart']['base_url'] = 'https://db.example.test';

    $business = BusinessSetting::query()->create([
        'name' => 'Test Biz',
        'settings' => $settings,
    ]);

    expect(ZHeartCredentials::baseUrl($business))->toBe('https://db.example.test')
        ->and(ZHeartCredentials::apiKey($business))->toBe($plain)
        ->and(ZHeartCredentials::hasStoredApiKey($business))->toBeTrue()
        ->and(ZHeartCredentials::storedKeyLast4($business))->toBe('ZZZZ');
});

it('falls back to env when database key is cleared', function () {
    config([
        'zheart.base_url' => 'https://env.example.test',
        'zheart.api_key' => 'zh_env1234.ABCDEFGHIJKLMNOP',
    ]);

    $settings = ZHeartCredentials::clearStoredKeyFromSettings(BusinessSetting::defaultSettings());
    $business = BusinessSetting::query()->create([
        'name' => 'Test Biz',
        'settings' => $settings,
    ]);

    expect(ZHeartCredentials::baseUrl($business))->toBe('https://env.example.test')
        ->and(ZHeartCredentials::apiKey($business))->toBe('zh_env1234.ABCDEFGHIJKLMNOP')
        ->and(ZHeartCredentials::hasStoredApiKey($business))->toBeFalse();
});

it('never persists plaintext api key in settings json', function () {
    $plain = 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789';
    $settings = ZHeartCredentials::applyKeyToSettings(BusinessSetting::defaultSettings(), $plain);

    expect($settings['zheart']['api_key_encrypted'])->not->toBe($plain)
        ->and(Crypt::decryptString($settings['zheart']['api_key_encrypted']))->toBe($plain);
});