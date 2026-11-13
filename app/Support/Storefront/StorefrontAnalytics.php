<?php

namespace App\Support\Storefront;

use App\Models\BusinessSetting;

final class StorefrontAnalytics
{
    /**
     * SEO/analytics payload for café storefront layout components.
     *
     * @return array{
     *     ga_measurement_id: ?string,
     *     gtm_container_id: ?string,
     *     meta_pixel_id: ?string,
     *     google_site_verification: ?string,
     *     bing_site_verification: ?string,
     * }
     */
    public static function forBusinessSetting(BusinessSetting $tenant): array
    {
        $config = StorefrontConfig::forBusinessSetting($tenant);

        return [
            'ga_measurement_id' => StorefrontConfig::gaMeasurementId($tenant),
            'gtm_container_id' => StorefrontConfig::gtmContainerId($tenant),
            'meta_pixel_id' => StorefrontConfig::metaPixelId($tenant),
            'google_site_verification' => StorefrontConfig::googleSiteVerification($tenant),
            'bing_site_verification' => StorefrontConfig::bingSiteVerification($tenant),
        ];
    }
}
