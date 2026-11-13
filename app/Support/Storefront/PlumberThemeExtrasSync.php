<?php

namespace App\Support\Storefront;

final class PlumberThemeExtrasSync
{
    /**
     * @param  array<string, mixed>  $settings  Tenant settings (mutated in place)
     * @param  array<string, mixed>  $extras
     */
    public static function applyToSettings(array &$settings, array $extras): void
    {
        $content = (array) data_get($settings, 'content.plumber', []);

        $areasRaw = trim((string) ($extras['service_areas'] ?? ''));
        $areas = $areasRaw === ''
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $areasRaw) ?: [])));

        $responseEn = trim((string) ($extras['response_time_en'] ?? ''));
        $responseAr = trim((string) ($extras['response_time_ar'] ?? ''));
        $license = trim((string) ($extras['license_number'] ?? ''));
        $emergency = (bool) ($extras['emergency_24_7'] ?? false);

        if ($areas !== [] || $license !== '') {
            $content['service_area'] = [
                'title_en' => __('plumber.service_area_title', [], 'en'),
                'title_ar' => __('plumber.service_area_title', [], 'ar'),
                'intro_en' => '',
                'intro_ar' => '',
                'areas_en' => $areas,
                'areas_ar' => $areas,
                'managed' => true,
            ];
        } else {
            unset($content['service_area']);
        }

        $content['booking'] = [
            'emergency_24_7' => $emergency,
            'license_number' => $license,
            'response_time_en' => $responseEn,
            'response_time_ar' => $responseAr,
        ];

        unset($content['emergency_note_en'], $content['emergency_note_ar']);

        data_set($settings, 'content.plumber', $content);
    }
}
