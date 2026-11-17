<?php

namespace App\Support\Invoicing;

use App\Business\BusinessContext;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Crypt;

final class ZHeartCredentials
{
    public static function baseUrl(?BusinessSetting $business = null): string
    {
        $business ??= self::business(null);

        if ($business !== null) {
            $stored = trim((string) data_get($business->settings, 'zheart.base_url', ''));
            if ($stored !== '') {
                return rtrim($stored, '/');
            }
        }

        return rtrim((string) config('zheart.base_url', ''), '/');
    }

    public static function hasStoredApiKey(?BusinessSetting $business = null): bool
    {
        $business ??= self::business(null);
        if ($business === null) {
            return false;
        }

        $encrypted = data_get($business->settings, 'zheart.api_key_encrypted');

        return is_string($encrypted) && $encrypted !== '';
    }

    public static function apiKey(?BusinessSetting $business = null): ?string
    {
        $business ??= self::business(null);

        if ($business !== null && self::hasStoredApiKey($business)) {
            try {
                $encrypted = (string) data_get($business->settings, 'zheart.api_key_encrypted', '');

                return Crypt::decryptString($encrypted);
            } catch (\Throwable) {
                return null;
            }
        }

        $env = config('zheart.api_key');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public static function storedKeyLast4(?BusinessSetting $business = null): ?string
    {
        if (! self::hasStoredApiKey($business)) {
            return null;
        }

        $business ??= self::business(null);
        if ($business === null) {
            return null;
        }

        $last4 = data_get($business->settings, 'zheart.api_key_last4');

        return is_string($last4) && strlen($last4) === 4 ? $last4 : null;
    }

    public static function isConfigured(?BusinessSetting $business = null): bool
    {
        return self::baseUrl($business) !== '' && filled(self::apiKey($business));
    }

    public static function last4ForDisplay(?BusinessSetting $business = null): ?string
    {
        if ($stored = self::storedKeyLast4($business)) {
            return $stored;
        }

        $key = self::apiKey($business);
        if ($key === null || $key === '') {
            return null;
        }

        return strlen($key) >= 4 ? substr($key, -4) : null;
    }

    /**
     * @return array{zheart: array<string, mixed>}
     */
    public static function applyKeyToSettings(array $settings, string $plainKey): array
    {
        $plainKey = trim($plainKey);
        $settings['zheart'] = array_merge(
            is_array($settings['zheart'] ?? null) ? $settings['zheart'] : [],
            [
                'api_key_encrypted' => Crypt::encryptString($plainKey),
                'api_key_last4' => strlen($plainKey) >= 4 ? substr($plainKey, -4) : '',
            ],
        );

        return $settings;
    }

  /**
   * @return array{zheart: array<string, mixed>}
   */
    public static function clearStoredKeyFromSettings(array $settings): array
    {
        $block = is_array($settings['zheart'] ?? null) ? $settings['zheart'] : [];
        unset($block['api_key_encrypted'], $block['api_key_last4']);
        $settings['zheart'] = $block;

        return $settings;
    }

    private static function business(?BusinessSetting $business): ?BusinessSetting
    {
        if ($business !== null) {
            return $business;
        }

        return BusinessContext::get();
    }
}