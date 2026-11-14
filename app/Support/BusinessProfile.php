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
        $parts = array_filter([
            trim(self::scalarString($na['building'] ?? '')),
            trim(self::scalarString($na['street_ar'] ?? $na['street'] ?? '')),
            trim(self::scalarString($na['district_ar'] ?? $na['district'] ?? '')),
            trim(self::scalarString($na['city_ar'] ?? $na['city'] ?? '')),
            trim(self::scalarString($na['postal_code'] ?? '')),
            trim(self::scalarString($na['short_national'] ?? $na['short_national_address'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        return $parts === [] ? null : implode('، ', $parts);
    }

    /**
     * @param  array<string, mixed>  $na
     */
    public static function formatNationalAddressEn(array $na): ?string
    {
        $parts = array_filter([
            trim(self::scalarString($na['building'] ?? '')),
            trim(self::scalarString($na['street'] ?? '')),
            trim(self::scalarString($na['district'] ?? '')),
            trim(self::scalarString($na['city'] ?? '')),
            trim(self::scalarString($na['postal_code'] ?? '')),
            trim(self::scalarString($na['additional'] ?? '')),
        ], static fn (string $v): bool => $v !== '');

        $country = strtoupper(self::scalarString($na['country'] ?? 'SA'));
        if ($country !== '' && $country !== 'SA') {
            $parts[] = $country;
        }

        return $parts === [] ? null : implode(', ', $parts);
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
     * @param  mixed  $stored
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
