<?php

namespace App\Support;

use App\Models\BusinessSetting;

/**
 * Canonical read/write paths for business profile stored under settings.business.
 */
final class BusinessProfile
{
    /**
     * @return array<string, mixed>
     */
    public static function contentSlice(BusinessSetting $business): array
    {
        return (array) data_get($business->settings, 'content.plumber', []);
    }

    public static function contentKey(BusinessSetting $business): string
    {
        return 'content.plumber';
    }

    /**
     * @return list<string>
     */
    public static function selectableBrandColorKeys(): array
    {
        return ['navy', 'steel', 'safety', 'accent'];
    }

    /**
     * @return array<string, string>
     */
    public static function defaultThemeColors(): array
    {
        return (array) data_get(BusinessSetting::defaultSettings(), 'theme.colors', []);
    }

    /**
     * @return array<string, string>
     */
    public static function brandColorPalette(BusinessSetting $business): array
    {
        $defaults = self::defaultThemeColors();
        $stored = $business->themeColors();
        $out = [];
        foreach (self::selectableBrandColorKeys() as $key) {
            $value = $stored[$key] ?? $defaults[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out !== [] ? $out : array_intersect_key($defaults, array_flip(self::selectableBrandColorKeys()));
    }

    /**
     * @return array<string, string>
     */
    public static function brandColorOptions(BusinessSetting $business): array
    {
        $labels = [
            'navy' => __('business.colors.navy'),
            'steel' => __('business.colors.steel'),
            'safety' => __('business.colors.safety'),
            'accent' => __('business.colors.accent'),
        ];

        $out = [];
        foreach (self::selectableBrandColorKeys() as $key) {
            if (isset($labels[$key])) {
                $out[$key] = $labels[$key];
            }
        }

        return $out;
    }

    public static function normalizeBrandColorKey(BusinessSetting $business, mixed $key): string
    {
        $key = self::scalarString($key);
        $allowed = array_keys(self::brandColorOptions($business));

        if ($key !== '' && in_array($key, $allowed, true)) {
            return $key;
        }

        return self::primaryColorKey($business);
    }

    public static function primaryColorKey(BusinessSetting $business): string
    {
        return 'navy';
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public static function mergeDefaultTheme(array $settings): array
    {
        $defaults = BusinessSetting::defaultSettings();
        $theme = (array) ($settings['theme'] ?? []);
        $defaultTheme = (array) ($defaults['theme'] ?? []);

        if (! isset($theme['layout_key']) || $theme['layout_key'] === '') {
            $theme['layout_key'] = (string) ($defaultTheme['layout_key'] ?? 'plumber');
        }

        $paletteDefaults = self::defaultThemeColors();
        $colors = (array) ($theme['colors'] ?? []);
        foreach ($paletteDefaults as $colorKey => $hex) {
            if (! isset($colors[$colorKey]) || $colors[$colorKey] === '') {
                $colors[$colorKey] = $hex;
            }
        }
        $theme['colors'] = $colors;
        $settings['theme'] = array_merge($defaultTheme, $theme);

        return $settings;
    }

    /**
     * @param  array<string, mixed>  $na
     */
    public static function formatNationalAddressAr(array $na): ?string
    {
        $parts = self::nationalAddressArParts($na);

        return $parts === [] ? null : implode('، ', $parts);
    }

    /**
     * RTL-friendly HTML for seller Arabic national address (plain legal address strings unchanged).
     *
     * @param  array<string, mixed>  $na
     */
    public static function formatNationalAddressArHtml(array $na): ?string
    {
        $parts = self::nationalAddressArParts($na);
        if ($parts === []) {
            return null;
        }

        $htmlParts = array_map(
            static fn (string $part): string => self::arabicAddressPartHtml($part),
            $parts,
        );

        return '<span class="invoice-print-ar invoice-national-address-ar" dir="rtl">'
            .implode('<span class="invoice-national-address-ar__sep" aria-hidden="true">، </span>', $htmlParts)
            .'</span>';
    }

    /**
     * Two explicit RTL lines for mPDF seller address (building/street, then district/city/postal).
     *
     * @param  array<string, mixed>  $na
     * @return list<string>
     */
    public static function formatNationalAddressArPdfLineHtml(array $na): array
    {
        $building = trim(self::scalarString($na['building'] ?? ''));
        $street = trim(self::scalarString($na['street_ar'] ?? $na['street'] ?? ''));

        $lines = [];

        $line1Text = match (true) {
            $building !== '' && $street !== '' => $building.' '.$street,
            $building !== '' => $building,
            $street !== '' => $street,
            default => null,
        };
        if ($line1Text !== null) {
            $lines[] = self::pdfAddressDigitRunsHtml($line1Text.'،');
        }

        $district = trim(self::scalarString($na['district_ar'] ?? $na['district'] ?? ''));
        $city = trim(self::scalarString($na['city_ar'] ?? $na['city'] ?? ''));
        $postal = trim(self::scalarString($na['postal_code'] ?? ''));

        $cityPostal = trim($city.($postal !== '' ? ' '.$postal : ''));
        $line2Text = match (true) {
            $district !== '' && $cityPostal !== '' => $district.'، '.$cityPostal,
            $district !== '' => $district,
            $cityPostal !== '' => $cityPostal,
            default => null,
        };
        if ($line2Text !== null) {
            $additional = trim(self::scalarString($na['additional'] ?? ''));
            if ($additional !== '') {
                $line2Text .= '، '.$additional;
            }
            // Keep district/city/postal in one RTL run so PDF engines do not pull LTR digit spans to the line start.
            $lines[] = e($line2Text);
        }

        return $lines;
    }

    /**
     * PDF-only: wrap Western digits in Dompdf seller Arabic address lines (screen HTML unchanged).
     */
    public static function wrapPdfAddressDigitRunsInEscapedLine(string $escapedLine): string
    {
        $plain = html_entity_decode($escapedLine, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::pdfAddressDigitRunsHtml($plain);
    }

    private static function pdfAddressDigitRunsHtml(string $text): string
    {
        $escaped = e($text);

        return preg_replace_callback(
            '/(\d+)/u',
            static fn (array $m): string => '<span dir="ltr" class="invoice-pdf-ltr-digits">'.$m[1].'</span>',
            $escaped,
        ) ?? $escaped;
    }

    /**
     * @param  array<string, mixed>  $na
     * @return list<string>
     */
    public static function nationalAddressArParts(array $na): array
    {
        $buildingStreet = self::nationalAddressBuildingStreetAr($na);

        return array_values(array_filter([
            $buildingStreet,
            trim(self::scalarString($na['district_ar'] ?? $na['district'] ?? '')),
            trim(self::scalarString($na['city_ar'] ?? $na['city'] ?? '')),
            trim(self::scalarString($na['postal_code'] ?? '')),
        ], static fn (string $v): bool => $v !== ''));
    }

    /**
     * @param  array<string, mixed>  $na
     */
    private static function nationalAddressBuildingStreetAr(array $na): ?string
    {
        $building = trim(self::scalarString($na['building'] ?? ''));
        $street = trim(self::scalarString($na['street_ar'] ?? $na['street'] ?? ''));

        if ($building !== '' && $street !== '') {
            return $building.' '.$street;
        }

        return $building !== '' ? $building : ($street !== '' ? $street : null);
    }

    private static function arabicAddressPartHtml(string $segment): string
    {
        $escaped = e($segment);
        $withLtrNums = preg_replace_callback(
            '/(\d+)/u',
            static fn (array $m): string => '<span dir="ltr" class="invoice-print-ltr-num">'.$m[1].'</span>',
            $escaped,
        ) ?? $escaped;

        return '<span class="invoice-national-address-ar__part" dir="rtl">'.$withLtrNums.'</span>';
    }

    /**
     * @param  array<string, mixed>  $na
     */
    public static function formatNationalAddressEn(array $na): ?string
    {
        $lines = self::formatNationalAddressEnPrintLines($na);
        if ($lines === []) {
            return null;
        }

        return implode(', ', $lines);
    }

    /**
     * Explicit EN lines for invoice print/PDF (building + street, district/city/postal, additional).
     * Omits country when SA; never duplicates district when equal to city.
     *
     * @param  array<string, mixed>  $na
     * @return list<string>
     */
    public static function formatNationalAddressEnPrintLines(array $na): array
    {
        $building = trim(self::scalarString($na['building'] ?? ''));
        $street = trim(self::scalarString($na['street'] ?? ''));
        $district = trim(self::scalarString($na['district'] ?? ''));
        $city = trim(self::scalarString($na['city'] ?? ''));
        $postal = trim(self::scalarString($na['postal_code'] ?? $na['postal'] ?? ''));
        $additional = trim(self::scalarString($na['additional'] ?? ''));

        $lines = [];

        $line1 = match (true) {
            $building !== '' && $street !== '' => $building.', '.$street,
            $building !== '' => $building,
            $street !== '' => $street,
            default => null,
        };
        if ($line1 !== null) {
            $lines[] = $line1;
        }

        $cityPostal = trim($city.($postal !== '' ? ' '.$postal : ''));
        $districtDistinct = $district !== ''
            && ($city === '' || strcasecmp($district, $city) !== 0);

        $line2 = match (true) {
            $districtDistinct && $cityPostal !== '' => $district.', '.$cityPostal,
            $districtDistinct => $district,
            $cityPostal !== '' => $cityPostal,
            default => null,
        };
        if ($line2 !== null) {
            if ($additional !== '') {
                $line2 .= ', '.$additional;
            }
            $lines[] = $line2;
        } elseif ($additional !== '') {
            $lines[] = $additional;
        }

        $country = strtoupper(self::scalarString($na['country'] ?? 'SA'));
        if ($country !== '' && $country !== 'SA') {
            $lines[] = $country;
        }

        return $lines;
    }

    /**
     * @return list<array{key: string, label: string, closed: bool, open: string, close: string}>
     */
    public static function defaultWeekdays(): array
    {
        $keys = ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $out = [];
        foreach ($keys as $key) {
            $out[] = [
                'key' => $key,
                'closed' => $key === 'friday',
                'open' => $key === 'friday' ? '14:00' : '07:00',
                'close' => '23:00',
            ];
        }

        return $out;
    }

    public static function scalarString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $value;
        }

        return '';
    }

    /**
     * @return list<array{key: string, closed: bool, open: string, close: string}>
     */
    public static function normalizeWeekdays(mixed $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return self::defaultWeekdays();
        }
        $out = [];
        foreach ($stored as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $out[] = [
                'key' => $key,
                'closed' => (bool) ($row['closed'] ?? false),
                'open' => self::scalarString($row['open'] ?? '09:00') ?: '09:00',
                'close' => self::scalarString($row['close'] ?? '22:00') ?: '22:00',
            ];
        }

        return $out === [] ? self::defaultWeekdays() : $out;
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, string>
     */
    public static function normalizeContactBlock(array $contact): array
    {
        $keys = ['phone', 'whatsapp', 'email', 'website', 'instagram', 'x', 'tiktok', 'snapchat', 'maps_url', 'lat', 'lng'];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = self::scalarString($contact[$key] ?? '');
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    public static function contactFromContent(array $content): array
    {
        $legacy = (array) ($content['contact'] ?? []);

        return [
            'phone' => self::scalarString($legacy['phone'] ?? ''),
            'whatsapp' => self::scalarString($legacy['whatsapp'] ?? ''),
            'email' => self::scalarString($legacy['email'] ?? ''),
            'instagram' => self::scalarString($legacy['instagram'] ?? ''),
        ];
    }
}
