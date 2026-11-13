<?php

namespace App\Services\Business;

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Storefront\StorefrontBusinessMirror;
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
    public function ensureHydrated(BusinessSetting $tenant): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $canonical = $this->canonicalBusinessBlock($tenant, $settings);
        $previous = data_get($settings, 'business');

        $settings['business'] = $canonical;
        StorefrontBusinessMirror::persistToContent($tenant, $settings);
        $before = json_encode($tenant->settings);
        $after = json_encode($settings);
        if ($before !== $after) {
            $tenant->forceFill(['settings' => $settings])->save();
        }

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function canonicalBusinessBlock(BusinessSetting $tenant, array $settings): array
    {
        $legal = (array) ($settings['legal_entity'] ?? []);
        $content = BusinessProfile::contentSlice($tenant);
        $existing = (array) data_get($settings, 'business', []);

        $brand = array_merge([
            'display_name_en' => BusinessProfile::scalarString($legal['name_en'] ?? $tenant->name),
            'display_name_ar' => BusinessProfile::scalarString($legal['name_ar'] ?? ''),
            'display_name_bn' => BusinessProfile::scalarString(data_get($existing, 'brand.display_name_bn', '')),
            'tagline_en' => BusinessProfile::scalarString($content['tagline_en'] ?? ''),
            'tagline_ar' => BusinessProfile::scalarString($content['tagline_ar'] ?? ''),
            'brand_color_key' => BusinessProfile::scalarString(
                data_get($existing, 'brand.brand_color_key') ?: BusinessProfile::primaryColorKey($tenant),
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
            $contentSlice = BusinessProfile::contentSlice($tenant);
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
            $this->defaultThemeExtras($tenant),
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
    public function saveBrand(BusinessSetting $tenant, array $input, ?StaffUser $actor = null, $logo = null): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $business = $this->canonicalBusinessBlock($tenant, $settings);
        $brand = (array) ($business['brand'] ?? []);

        foreach (['display_name_en', 'display_name_ar', 'display_name_bn', 'tagline_en', 'tagline_ar', 'brand_color_key'] as $key) {
            if (array_key_exists($key, $input)) {
                $brand[$key] = $input[$key];
            }
        }
        $business['brand'] = $brand;
        $settings['business'] = $business;

        $legal = (array) ($settings['legal_entity'] ?? []);
        $legal['name_en'] = (string) ($brand['display_name_en'] ?? $legal['name_en'] ?? '');
        $legal['name_ar'] = (string) ($brand['display_name_ar'] ?? $legal['name_ar'] ?? '');
        $settings['legal_entity'] = $legal;

        $contentKey = BusinessProfile::contentKey($tenant);
        $content = (array) data_get($settings, $contentKey, []);
        $content['tagline_en'] = (string) ($brand['tagline_en'] ?? '');
        $content['tagline_ar'] = (string) ($brand['tagline_ar'] ?? '');
        data_set($settings, $contentKey, $content);

        $colorKey = (string) ($brand['brand_color_key'] ?? BusinessProfile::primaryColorKey($tenant));
        $theme = (array) ($settings['theme'] ?? []);
        $palette = $tenant->themeColors();
        if (isset($palette[$colorKey])) {
            $primaryKey = BusinessProfile::primaryColorKey($tenant);
            $colors = (array) ($theme['colors'] ?? []);
            $colors[$primaryKey] = $palette[$colorKey];
            $theme['colors'] = $colors;
            $settings['theme'] = $theme;
        }

        if ($logo !== null) {
            $previous = data_get($settings, 'theme.logo_url');
            $relative = $this->logos->store($tenant, $logo);
            data_set($settings, 'theme.logo_url', $relative);
            data_set($settings, 'business.brand.favicon_path', 'img/business/'.$tenant->id.'/favicon.png');
            if ($previous && $previous !== $relative) {
                $this->logos->deleteTenantLogos($tenant, $relative);
            }
        }

        $tenant->name = (string) ($legal['name_en'] ?: $tenant->name);
        $tenant->forceFill(['settings' => $settings, 'name' => $tenant->name])->save();

        $this->audit->record($tenant, 'business.settings.brand', ['brand' => $brand], $actor);

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveLegal(BusinessSetting $tenant, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $business = $this->canonicalBusinessBlock($tenant, $settings);
        $legalBlock = (array) ($business['legal'] ?? []);
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
        $business['legal'] = $legalBlock;
        $settings['business'] = $business;

        $legal = (array) ($settings['legal_entity'] ?? []);
        $legal['name_en'] = (string) ($legalBlock['name_en'] ?? '');
        $legal['name_ar'] = (string) ($legalBlock['name_ar'] ?? '');
        $legal['vat_number'] = preg_replace('/\D/', '', (string) ($legalBlock['vat_number'] ?? '')) ?? '';
        $legal['cr_number'] = (string) ($legalBlock['cr_number'] ?? '');
        $legal['address'] = (string) ($legalBlock['address_en'] ?? '');
        $legal['address_ar'] = (string) ($legalBlock['address_ar'] ?? '');
        $legal['national_address'] = (array) ($legalBlock['national_address'] ?? []);
        $settings['legal_entity'] = $legal;

        $tenant->forceFill(['settings' => $settings])->save();
        $this->audit->record($tenant, 'business.settings.legal', ['vat' => $legal['vat_number']], $actor);

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveContact(BusinessSetting $tenant, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $business = $this->canonicalBusinessBlock($tenant, $settings);
        $contact = BusinessProfile::normalizeContactBlock(array_merge(
            (array) ($business['contact'] ?? []),
            $input,
        ));
        $business['contact'] = $contact;
        $settings['business'] = $business;

        $contentKey = BusinessProfile::contentKey($tenant);
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

        $tenant->forceFill(['settings' => $settings])->save();
        $this->audit->record($tenant, 'business.settings.contact', [], $actor);

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveHours(BusinessSetting $tenant, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $business = $this->canonicalBusinessBlock($tenant, $settings);
        $hours = (array) ($business['hours'] ?? []);
        if (isset($input['weekdays']) && is_array($input['weekdays'])) {
            $hours['weekdays'] = BusinessProfile::normalizeWeekdays($input['weekdays']);
        }
        if (array_key_exists('note', $input)) {
            $hours['note'] = BusinessProfile::scalarString($input['note']);
        }
        $business['hours'] = $hours;
        $settings['business'] = $business;

        $contentKey = BusinessProfile::contentKey($tenant);
        $content = (array) data_get($settings, $contentKey, []);
        $content['hours'] = BusinessHours::toLegacyRows(
            (array) ($hours['weekdays'] ?? []),
            filled($hours['note'] ?? null) ? (string) $hours['note'] : null,
        );
        data_set($settings, $contentKey, $content);

        $tenant->forceFill(['settings' => $settings])->save();
        $this->audit->record($tenant, 'business.settings.hours', [], $actor);

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveThemeExtras(BusinessSetting $tenant, array $input, ?StaffUser $actor = null): BusinessSetting
    {
        $settings = $tenant->settings ?? BusinessSetting::defaultSettings();
        $business = $this->canonicalBusinessBlock($tenant, $settings);
        $extras = array_merge((array) ($business['theme_extras'] ?? []), $input);
        $business['theme_extras'] = $extras;
        $settings['business'] = $business;
        $settings['business']['theme_extras'] = $extras;

        StorefrontBusinessMirror::persistToContent($tenant, $settings);

        $tenant->forceFill(['settings' => $settings])->save();
        $tenant->refresh();
        $this->audit->record($tenant, 'business.settings.theme_extras', ['keys' => array_keys($input)], $actor);

        return $tenant->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultThemeExtras(BusinessSetting $tenant): array
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
