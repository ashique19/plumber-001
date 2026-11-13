<?php

namespace App\Support\Storefront;

use App\Models\Tenant;
use App\Support\TenantDisplayName;

final class PlumberBookingExtras
{
    /**
     * @param  array<string, mixed>  $content
     */
    public static function emergencyNote(Tenant $tenant, array $content, string $locale): string
    {
        $isRtl = TenantDisplayName::usesRtlName($locale);
        $booking = (array) ($content['booking'] ?? []);
        $emergencyOn = ($booking['emergency_24_7'] ?? false) === true;

        $response = $isRtl
            ? trim((string) ($booking['response_time_ar'] ?? ''))
            : trim((string) ($booking['response_time_en'] ?? ''));

        if ($response !== '') {
            return $response;
        }

        if (! $emergencyOn) {
            return '';
        }

        $fromContent = $isRtl
            ? trim((string) ($content['emergency_note_ar'] ?? ''))
            : trim((string) ($content['emergency_note_en'] ?? ''));

        return $fromContent !== '' ? $fromContent : __('plumber.emergency_note');
    }

    public static function licenseNumber(Tenant $tenant, array $content): string
    {
        $fromBooking = trim((string) data_get($content, 'booking.license_number', ''));

        if ($fromBooking !== '') {
            return $fromBooking;
        }

        return trim((string) data_get($tenant->settings, 'business.theme_extras.license_number', ''));
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function showEmergencyBadge(Tenant $tenant, array $content): bool
    {
        $booking = (array) ($content['booking'] ?? []);

        return ($booking['emergency_24_7'] ?? false) === true;
    }
}
