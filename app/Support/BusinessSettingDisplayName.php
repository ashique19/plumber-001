<?php

namespace App\Support;

use App\Models\BusinessSetting;

final class BusinessSettingDisplayName
{
    /**
     * Locale-aware legal / brand name for staff auth and admin shell.
     */
    public static function for(BusinessSetting $business, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $brand = data_get($business->settings, 'business.brand', []);
        $legal = data_get($business->settings, 'legal_entity', []);
        $useRtlName = EnabledLocales::direction($locale) === 'rtl';

        if ($locale === 'bn' && filled($brand['display_name_bn'] ?? null)) {
            return (string) $brand['display_name_bn'];
        }

        return $useRtlName
            ? (string) ($brand['display_name_ar'] ?? $legal['name_ar'] ?? $business->name)
            : (string) ($brand['display_name_en'] ?? $legal['name_en'] ?? $business->name);
    }

    public static function usesRtlName(?string $locale = null): bool
    {
        return EnabledLocales::direction($locale ?? app()->getLocale()) === 'rtl';
    }
}
