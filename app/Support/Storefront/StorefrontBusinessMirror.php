<?php

namespace App\Support\Storefront;

use App\Models\BusinessSetting;
use App\Support\BusinessHours;
use App\Support\BusinessProfile;

/**
 * Publishes settings.business onto legacy content.* blocks.
 */
final class StorefrontBusinessMirror
{
    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function overlay(BusinessSetting $business, array $content): array
    {
        $settings = $business->settings ?? BusinessSetting::defaultSettings();
        $stored = (array) data_get($settings, 'business', []);

        $hours = (array) ($stored['hours'] ?? []);
        $weekdays = BusinessProfile::normalizeWeekdays((array) ($hours['weekdays'] ?? []));
        if ($weekdays !== []) {
            $note = BusinessProfile::scalarString($hours['note'] ?? '');
            $content['hours'] = BusinessHours::toLegacyRows(
                $weekdays,
                filled($note) ? $note : null,
            );
        }

        $contact = (array) ($stored['contact'] ?? []);
        $legacyContact = (array) ($content['contact'] ?? []);
        foreach (['phone', 'whatsapp', 'email', 'instagram'] as $key) {
            if (array_key_exists($key, $contact) && (string) $contact[$key] !== '') {
                $legacyContact[$key] = (string) $contact[$key];
            }
        }
        if ($legacyContact !== []) {
            $content['contact'] = $legacyContact;
        }

        $lat = BusinessProfile::scalarString($contact['lat'] ?? '');
        $lng = BusinessProfile::scalarString($contact['lng'] ?? '');
        if ($lat !== '' && $lng !== '' && is_numeric($lat) && is_numeric($lng)) {
            $content['location'] = array_merge((array) ($content['location'] ?? []), [
                'lat' => round((float) $lat, 6),
                'lng' => round((float) $lng, 6),
                'map_placeholder' => false,
            ]);
        }

        if ($business->isPlumberTheme() && is_array(data_get($settings, 'business'))) {
            $settingsCopy = $settings;
            $extras = (array) ($stored['theme_extras'] ?? []);
            PlumberThemeExtrasSync::applyToSettings($settingsCopy, $extras);
            $plumberContent = (array) data_get($settingsCopy, 'content.plumber', []);
            foreach (['booking', 'emergency_note_en', 'emergency_note_ar'] as $key) {
                if (array_key_exists($key, $plumberContent)) {
                    $content[$key] = $plumberContent[$key];
                } else {
                    unset($content[$key]);
                }
            }
            if (array_key_exists('service_area', $plumberContent)) {
                $content['service_area'] = $plumberContent['service_area'];
            }
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public static function persistToContent(BusinessSetting $business, array &$settings): void
    {
        $stored = (array) data_get($settings, 'business', []);
        $contentKey = BusinessProfile::contentKey($business);
        $content = (array) data_get($settings, $contentKey, []);

        $hours = (array) ($stored['hours'] ?? []);
        $weekdays = BusinessProfile::normalizeWeekdays((array) ($hours['weekdays'] ?? []));
        if ($weekdays !== []) {
            $note = BusinessProfile::scalarString($hours['note'] ?? '');
            $content['hours'] = BusinessHours::toLegacyRows(
                $weekdays,
                filled($note) ? $note : null,
            );
        }

        $contact = (array) ($stored['contact'] ?? []);
        $legacyContact = (array) ($content['contact'] ?? []);
        foreach (['phone', 'whatsapp', 'email', 'instagram'] as $key) {
            if (array_key_exists($key, $contact)) {
                $legacyContact[$key] = (string) $contact[$key];
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

        if ($business->isPlumberTheme()) {
            PlumberThemeExtrasSync::applyToSettings($settings, (array) ($stored['theme_extras'] ?? []));
            $plumberContent = (array) data_get($settings, 'content.plumber', []);
            $content = array_merge($content, $plumberContent);
        }

        data_set($settings, $contentKey, $content);
    }
}
