<?php

namespace App\Support\Geo;

final class SaudiCities
{
    public const OTHER_KEY = 'other';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return config('saudi_cities.keys', []);
    }

    public static function englishName(string $key): string
    {
        return (string) __('saudi_cities.'.$key, [], 'en');
    }

    public static function localizedLabel(string $key, ?string $locale = null): string
    {
        return (string) __(
            $key === self::OTHER_KEY ? 'saudi_cities.other' : 'saudi_cities.'.$key,
            [],
            $locale ?? app()->getLocale()
        );
    }

    /**
     * @return list<string>
     */
    public static function allEnglishNames(): array
    {
        return array_map(fn (string $key): string => self::englishName($key), self::keys());
    }

    /**
     * @return array{mode: 'listed'|'other'|'empty', key: ?string, english: string}
     */
    public static function resolveStored(string $stored): array
    {
        $stored = trim($stored);
        if ($stored === '') {
            return ['mode' => 'empty', 'key' => null, 'english' => ''];
        }

        $normalized = strtolower($stored);
        foreach (config('saudi_cities.aliases', []) as $alias => $key) {
            if ($normalized === strtolower((string) $alias)) {
                $key = is_string($key) ? $key : (string) $key;
                if (in_array($key, self::keys(), true)) {
                    return ['mode' => 'listed', 'key' => $key, 'english' => self::englishName($key)];
                }
            }
        }

        foreach (self::keys() as $key) {
            $english = self::englishName($key);
            if (strcasecmp($stored, $english) === 0) {
                return ['mode' => 'listed', 'key' => $key, 'english' => $english];
            }

            foreach (['en', 'bn', 'ar'] as $locale) {
                $label = self::localizedLabel($key, $locale);
                if ($label !== '' && strcasecmp($stored, $label) === 0) {
                    return ['mode' => 'listed', 'key' => $key, 'english' => $english];
                }
            }
        }

        return ['mode' => 'other', 'key' => self::OTHER_KEY, 'english' => $stored];
    }

    public static function normalizeForStorage(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return self::resolveStored($value)['english'];
    }

  /**
   * @return list<array{key: string, label: string, english: string, ar: string}>
   */
    public static function comboboxOptions(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $options = [];
        foreach (self::keys() as $key) {
            $options[] = [
                'key' => $key,
                'label' => self::localizedLabel($key, $locale),
                'english' => self::englishName($key),
                'ar' => (string) __('saudi_cities.'.$key, [], 'ar'),
            ];
        }

        $options[] = [
            'key' => self::OTHER_KEY,
            'label' => self::localizedLabel(self::OTHER_KEY, $locale),
            'english' => '',
            'ar' => '',
        ];

        usort($options, fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }
}