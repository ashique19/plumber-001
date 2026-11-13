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
   * @return array<string, string>
   */
  public static function brandColorOptions(BusinessSetting $business): array
  {
      $colors = $business->themeColors();
      $labels = [
          'navy' => __('business.colors.navy'),
          'steel' => __('business.colors.steel'),
          'safety' => __('business.colors.safety'),
          'accent' => __('business.colors.accent'),
      ];

      $out = [];
      foreach ($labels as $key => $label) {
          if (isset($colors[$key])) {
              $out[$key] = $label;
          }
      }

      return $out;
  }

  public static function primaryColorKey(BusinessSetting $business): string
  {
      return 'navy';
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
