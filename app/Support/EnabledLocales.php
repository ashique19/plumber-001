<?php

namespace App\Support;

final class EnabledLocales
{
    /**
     * Locales users may pick in UI switchers (re-enable hidden codes via config only).
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        $configured = config('app.enabled_locales', ['en', 'ar', 'bn']);
        if (! is_array($configured)) {
            $configured = ['en', 'ar', 'bn'];
        }

        $supported = array_keys(config('locales.supported', []));

        return collect($configured)
            ->filter(fn ($code) => is_string($code) && $code !== '' && in_array($code, $supported, true))
            ->unique()
            ->values()
            ->all();
    }

    public static function isEnabled(string $locale): bool
    {
        return in_array($locale, self::codes(), true);
    }

    /**
     * Map session/query/tenant locale to an enabled code; disabled codes fall back gracefully.
     */
    public static function resolve(?string $locale, ?string $fallback = null): string
    {
        $codes = self::codes();
        $fallback = $fallback ?? (string) config('locales.default', 'en');
        $locale = is_string($locale) ? strtolower(trim($locale)) : '';

        if ($locale !== '' && in_array($locale, $codes, true)) {
            return $locale;
        }

        $fallback = strtolower(trim($fallback));
        if (in_array($fallback, $codes, true)) {
            return $fallback;
        }

        return $codes[0] ?? 'en';
    }

    public static function direction(string $locale): string
    {
        return (string) config("locales.supported.{$locale}.dir", 'ltr');
    }

    /**
     * @return list<string>
     */
    public static function codesWithLangFile(string $fileBase): array
    {
        return collect(self::codes())
            ->filter(fn (string $code) => is_file(lang_path("{$code}/{$fileBase}.php")))
            ->values()
            ->all();
    }
}
