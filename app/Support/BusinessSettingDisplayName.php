<?php

namespace App\Support;

use App\Models\BusinessSetting;

final class BusinessSettingDisplayName
{
    /**
     * Locale-aware legal / brand name for public café surfaces and tenant auth.
     */
    public static function for(BusinessSetting $tenant, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $brand = data_get($tenant->settings, 'business.brand', []);
        $legal = data_get($tenant->settings, 'legal_entity', []);
        $useRtlName = in_array($locale, ['ar', 'ur'], true);

        if ($locale === 'bn' && filled($brand['display_name_bn'] ?? null)) {
            return (string) $brand['display_name_bn'];
        }

        return $useRtlName
            ? (string) ($brand['display_name_ar'] ?? $legal['name_ar'] ?? $tenant->name)
            : (string) ($brand['display_name_en'] ?? $legal['name_en'] ?? $tenant->name);
    }

    public static function usesRtlName(?string $locale = null): bool
    {
        return in_array($locale ?? app()->getLocale(), ['ar', 'ur'], true);
    }
}
