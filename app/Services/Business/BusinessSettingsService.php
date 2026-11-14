<?php

namespace App\Services\Business;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Storefront\BusinessContentMirror;
use App\Support\BusinessHours;
use App\Support\BusinessProfile;

class BusinessSettingsService
{
    public function __construct(
        private readonly BusinessSettingsAuditService $audit,
        private readonly BusinessLogoService $logos,
    ) {}

    /**
     * Ensure settings.business exists and mirrors current legal/content data (idempotent).
     */
    public function ensureHydrated(BusinessSetting $business): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $canonical = $this->canonicalBusinessBlock($business, $settings);
        $previous = data_get($settings, 'business');

        $settings['business'] = $canonical;
        BusinessContentMirror::persistToContent($business, $settings);
        $before = json_encode($business->settings);
        $after = json_encode($settings);
        if ($before !== $after) {
            $business->forceFill(['settings' => $settings])->save();
        }

        return $business->fresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function canonicalBusinessBlock(BusinessSetting $business, array $settings): array
    {
        $legal = (array) ($settings['legal_entity'] ?? []);
        $content = BusinessProfile::contentSlice($business);
        $existing = (array) data_get($settings, 'business', []);

        $brand = array_merge([
            'display_name_en' => BusinessProfile::scalarString($legal['name_en'] ?? $business->name),
            'display_name_ar' => BusinessProfile::scalarString($legal['name_ar'] ?? ''),
            'display_name_bn' => BusinessProfile::scalarString(data_get($existing, 'brand.display_name_bn', '')),
            'tagline_en' => BusinessProfile::scalarString($content['tagline_en'] ?? ''),
            'tagline_ar' => BusinessProfile::scalarString($content['tagline_ar'] ?? ''),
            'brand_color_key' => BusinessProfile::scalarString(
                data_get($existing, 'brand.brand_color_key') ?: BusinessProfile::primaryColorKey($business),
            ),
        ], (array) ($existing['brand'] ?? []));

        $defaultNa = BusinessSetting::defaultSettings()['legal_entity']['national_address'];
        $legalBlock = array_merge([
            'name_en' => BusinessProfile::scalarString($legal['name_en'] ?? ''),
            'name_ar' => BusinessProfile::scalarString($legal['name_ar'] ?? ''),
            'vat_number' => BusinessProfile::scalarString($legal['vat_number'] ?? ''),
            'cr_number' => BusinessProfile::scalarString($legal['cr_number'] ?? ''),
            'national_address' => (array) ($legal['national_address'] ?? $defaultNa),
            'address_en' => BusinessProfile::scalarString($legal['address'] ?? ''),
            'address_ar' => BusinessProfile::scalarString($legal['address_ar'] ?? ''),
        ], (array) ($existing['legal'] ?? []));

        $contactDefaults = [
            'phone' => '',
            'whatsapp' => '',
            'email' => '',
            'website' => '',
            'instagram' => '',
            'x' => '',
            'tiktok' => '',
            'snapchat' => '',
            'maps_url' => '',
            'lat' => '',
            'lng' => '',
        ];
        $contact = array_merge(
            $contactDefaults,
            BusinessProfile::contactFromContent($content),
            BusinessProfile::normalizeContactBlock((array) ($existing['contact'] ?? [])),
        );

        $storedWeekdays = data_get($existing, 'hours.weekdays');
        $weekdays = BusinessProfile::normalizeWeekdays($storedWeekdays);
        if ($storedWeekdays === null || $storedWeekdays === []) {
            $contentSlice = BusinessProfile::contentSlice($business);
            $inferred = BusinessHours::weekdaysFromLegacyRows((array) ($contentSlice['hours'] ?? []));
            if ($inferred !== null) {
                $weekdays = BusinessProfile::normalizeWeekdays($inferred);
            }
        }

        $hours = [
            'weekdays' => $weekdays,
            'note' => BusinessProfile::scalarString(data_get($existing, 'hours.note', '')),
        ];

        $extras = array_merge(
            $this->defaultThemeExtras($business),
            (array) ($existing['theme_extras'] ?? []),
        );

        return [
            'brand' => $brand,
            'legal' => $legalBlock,
            'contact' => $contact,
            'hours' => $hours,
            'theme_extras' => $extras,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveBrand(BusinessSetting $business, array $input, ?StaffUser $actor = null, $logo = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $block = $this->canonicalBusinessBlock($business, $settings);
        $brand = (array) ($block['brand'] ?? []);

        foreach (['display_name_en', 'display_name_ar', 'display_name_bn', 'tagline_en', 'tagline_ar', 'brand_color_key'] as $key) {
            if (array_key_exists($key, $input)) {
                $brand[$key] = $input[$key];
            }
        }
        $block['brand'] = $brand;
        $settings['business'] = $block;

        $legal = (array) ($settings['legal_entity'] ?? []);
        $legal['name_en'] = (string) ($brand['display_name_en'] ?? $legal['name_en'] ?? '');
        $legal['name_ar'] = (string) ($brand['display_name_ar'] ?? $legal['name_ar'] ?? '');
        $settings['legal_entity'] = $legal;

        $contentKey = BusinessProfile::contentKey($business);
        $content = (array) data_get($settings, $contentKey, []);
        $content['tagline_en'] = (string) ($brand['tagline_en'] ?? '');
        $content['tagline_ar'] = (string) ($brand['tagline_ar'] ?? '');
        data_set($settings, $contentKey, $content);

        $colorKey = (string) ($brand['brand_color_key'] ?? BusinessProfile::primaryColorKey($business));
        $theme = (array) ($settings['theme'] ?? []);
        $palette = $business->themeColors();
        if (isset($palette[$colorKey])) {
            $primaryKey = BusinessProfile::primaryColorKey($business);
            $colors = (array) ($theme['colors'] ?? []);
            $colors[$primaryKey] = $palette[$colorKey];
            $theme['colors'] = $colors;
            $settings['theme'] = $theme;
        }

        if ($logo !== null) {
            $previous = data_get($settings, 'theme.logo_url');
            $relative = $this->logos->store($business, $logo);
            data_set($settings, 'theme.logo_url', $relative);
            data_set($settings, 'business.brand.favicon_path', 'img/business/'.$business->id.'/favicon.png');
            if ($previous && $previous !== $relative) {
                $this->logos->deleteBusinessLogos($business, $relative);
            }
        }

        $business->name = (string) ($legal['name_en'] ?: $business->name);
        $business->forceFill(['settings' => $settings, 'name' => $business->name])->save();

        $this->audit->record($business, 'business.settings.brand', ['brand' => $brand], $actor);

        return $business->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveLegal(BusinessSetting $business, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $block = $this->canonicalBusinessBlock($business, $settings);
        $legalBlock = (array) ($block['legal'] ?? []);
        foreach (['name_en', 'name_ar', 'vat_number', 'cr_number', 'address_en', 'address_ar'] as $key) {
            if (array_key_exists($key, $input)) {
                $legalBlock[$key] = $input[$key];
            }
        }
        if (array_key_exists('national_address', $input) && is_array($input['national_address'])) {
            $legalBlock['national_address'] = array_merge(
                (array) ($legalBlock['national_address'] ?? []),
                $input['national_address'],
            );
        }
        $block['legal'] = $legalBlock;
        $settings['business'] = $block;

        $legal = (array) ($settings['legal_entity'] ?? []);
        $legal['name_en'] = (string) ($legalBlock['name_en'] ?? '');
        $legal['name_ar'] = (string) ($legalBlock['name_ar'] ?? '');
        $legal['vat_number'] = preg_replace('/\D/', '', (string) ($legalBlock['vat_number'] ?? '')) ?? '';
        $legal['cr_number'] = (string) ($legalBlock['cr_number'] ?? '');
        $legal['address'] = (string) ($legalBlock['address_en'] ?? '');
        $legal['address_ar'] = (string) ($legalBlock['address_ar'] ?? '');
        $legal['national_address'] = (array) ($legalBlock['national_address'] ?? []);
        $settings['legal_entity'] = $legal;

        $business->forceFill(['settings' => $settings])->save();
        $this->audit->record($business, 'business.settings.legal', ['vat' => $legal['vat_number']], $actor);

        return $business->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveContact(BusinessSetting $business, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $block = $this->canonicalBusinessBlock($business, $settings);
        $contact = BusinessProfile::normalizeContactBlock(array_merge(
            (array) ($block['contact'] ?? []),
            $input,
        ));
        $block['contact'] = $contact;
        $settings['business'] = $block;

        $contentKey = BusinessProfile::contentKey($business);
        $content = (array) data_get($settings, $contentKey, []);
        $legacyContact = (array) ($content['contact'] ?? []);
        foreach (['phone', 'whatsapp', 'email', 'instagram'] as $key) {
            if (isset($contact[$key])) {
                $legacyContact[$key] = $contact[$key];
            }
        }
        $content['contact'] = $legacyContact;
        $lat = BusinessProfile::scalarString($contact['lat'] ?? '');
        $lng = BusinessProfile::scalarString($contact['lng'] ?? '');
        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            $content['location'] = array_merge((array) ($content['location'] ?? []), [
                'lat' => round((float) $lat, 6),
                'lng' => round((float) $lng, 6),
                'map_placeholder' => false,
            ]);
        }
        data_set($settings, $contentKey, $content);

        $business->forceFill(['settings' => $settings])->save();
        $this->audit->record($business, 'business.settings.contact', [], $actor);

        return $business->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveHours(BusinessSetting $business, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $block = $this->canonicalBusinessBlock($business, $settings);
        $hours = (array) ($block['hours'] ?? []);
        if (isset($input['weekdays']) && is_array($input['weekdays'])) {
            $hours['weekdays'] = BusinessProfile::normalizeWeekdays($input['weekdays']);
        }
        if (array_key_exists('note', $input)) {
            $hours['note'] = BusinessProfile::scalarString($input['note']);
        }
        $block['hours'] = $hours;
        $settings['business'] = $block;

        $contentKey = BusinessProfile::contentKey($business);
        $content = (array) data_get($settings, $contentKey, []);
        $content['hours'] = BusinessHours::toLegacyRows(
            (array) ($hours['weekdays'] ?? []),
            filled($hours['note'] ?? null) ? (string) $hours['note'] : null,
        );
        data_set($settings, $contentKey, $content);

        $business->forceFill(['settings' => $settings])->save();
        $this->audit->record($business, 'business.settings.hours', [], $actor);

        return $business->fresh();
    }

    /**
     * @param  array{integration_enabled?: bool}  $input
     */
    public function saveInvoicing(BusinessSetting $business, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $invoicing = (array) ($settings['invoicing'] ?? BusinessSetting::defaultSettings()['invoicing']);
        if (array_key_exists('integration_enabled', $input)) {
            $invoicing['integration_enabled'] = (bool) $input['integration_enabled'];
        }
        $settings['invoicing'] = $invoicing;

        $business->forceFill(['settings' => $settings])->save();
        $this->audit->record($business, 'business.settings.invoicing', [
            'integration_enabled' => $invoicing['integration_enabled'] ?? false,
        ], $actor);

        return $business->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveThemeExtras(BusinessSetting $business, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $block = $this->canonicalBusinessBlock($business, $settings);
        $extras = array_merge((array) ($block['theme_extras'] ?? []), $input);
        $block['theme_extras'] = $extras;
        $settings['business'] = $block;

        BusinessContentMirror::persistToContent($business, $settings);

        $business->forceFill(['settings' => $settings])->save();
        $business->refresh();
        $this->audit->record($business, 'business.settings.theme_extras', ['keys' => array_keys($input)], $actor);

        return $business->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultThemeExtras(BusinessSetting $business): array
    {
        return [
            'service_areas' => '',
            'emergency_24_7' => false,
            'license_number' => '',
            'response_time_en' => '',
            'response_time_ar' => '',
        ];
    }
}
