<?php

namespace App\Support\Storefront;

use App\Models\BusinessSetting;
use Illuminate\Support\Str;

final class StorefrontConfig
{
    public const SETTINGS_KEY = 'storefront';

    /**
     * @return array{
     *     ga_measurement_id: string,
     *     google_site_verification: string,
     *     bing_site_verification: string,
     *     gtm_container_id: string,
     *     meta_pixel_id: string,
     * }
     */
    public static function forBusinessSetting(BusinessSetting $tenant): array
    {
        $raw = (array) data_get($tenant->settings, self::SETTINGS_KEY, []);

        return [
            'ga_measurement_id' => trim((string) ($raw['ga_measurement_id'] ?? '')),
            'google_site_verification' => trim((string) ($raw['google_site_verification'] ?? '')),
            'bing_site_verification' => trim((string) ($raw['bing_site_verification'] ?? '')),
            'gtm_container_id' => trim((string) ($raw['gtm_container_id'] ?? '')),
            'meta_pixel_id' => trim((string) ($raw['meta_pixel_id'] ?? '')),
        ];
    }

    public static function gaMeasurementId(BusinessSetting $tenant): ?string
    {
        $id = self::forBusinessSetting($tenant)['ga_measurement_id'];

        return self::isValidGa4Id($id) ? $id : null;
    }

    public static function gtmContainerId(BusinessSetting $tenant): ?string
    {
        $id = self::forBusinessSetting($tenant)['gtm_container_id'];

        return self::isValidGtmContainerId($id) ? Str::upper($id) : null;
    }

    public static function metaPixelId(BusinessSetting $tenant): ?string
    {
        $id = self::forBusinessSetting($tenant)['meta_pixel_id'];

        return self::isValidMetaPixelId($id) ? $id : null;
    }

    public static function googleSiteVerification(BusinessSetting $tenant): ?string
    {
        $token = self::forBusinessSetting($tenant)['google_site_verification'];

        return self::isValidGoogleSiteVerification($token) && $token !== '' ? $token : null;
    }

    public static function bingSiteVerification(BusinessSetting $tenant): ?string
    {
        $token = self::forBusinessSetting($tenant)['bing_site_verification'];

        return self::isValidBingSiteVerification($token) && $token !== '' ? $token : null;
    }

    public static function isValidGa4Id(string $id): bool
    {
        return $id !== '' && (bool) preg_match('/^G-[A-Z0-9]{4,}$/i', $id);
    }

    public static function isValidGoogleSiteVerification(string $token): bool
    {
        if ($token === '') {
            return true;
        }

        return strlen($token) <= 120 && (bool) preg_match('/^[a-zA-Z0-9_\-+=\/\.]+$/', $token);
    }

    public static function isValidBingSiteVerification(string $token): bool
    {
        if ($token === '') {
            return true;
        }

        return strlen($token) <= 120 && (bool) preg_match('/^[A-F0-9]{16,64}$/i', $token);
    }

    public static function isValidGtmContainerId(string $id): bool
    {
        return $id !== '' && (bool) preg_match('/^GTM-[A-Z0-9]{4,}$/i', $id);
    }

    public static function isValidMetaPixelId(string $id): bool
    {
        return $id !== '' && (bool) preg_match('/^\d{10,20}$/', $id);
    }

    /**
     * @param  array{
     *     ga_measurement_id?: string,
     *     google_site_verification?: string,
     *     bing_site_verification?: string,
     *     gtm_container_id?: string,
     *     meta_pixel_id?: string,
     * }  $patch
     */
    public static function mergeIntoBusinessSetting(BusinessSetting $tenant, array $patch): void
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $current = (array) ($settings[self::SETTINGS_KEY] ?? []);
        $settings[self::SETTINGS_KEY] = array_merge($current, $patch);
        $tenant->forceFill(['settings' => $settings])->save();
    }

    public static function normalizeGaInput(string $value): string
    {
        $value = trim($value);

        return Str::upper($value);
    }
}
