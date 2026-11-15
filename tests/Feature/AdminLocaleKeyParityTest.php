<?php

/**
 * Ensure bn and ar locale files expose every key defined in en.
 */
it('exposes all en translation keys in bn and ar', function () {
    $flatten = null;
    $flatten = function (array $items, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                $keys = array_merge($keys, $flatten($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        return $keys;
    };

    $mergeLocale = function (string $locale, string $filename): array {
        $enPath = lang_path('en/'.$filename);
        if (! is_file($enPath)) {
            return [];
        }

        /** @var array<string, mixed> $en */
        $en = require $enPath;
        $localePath = lang_path($locale.'/'.$filename);

        if (! is_file($localePath)) {
            return $en;
        }

        /** @var array<string, mixed> $patch */
        $patch = require $localePath;

        return array_replace_recursive($en, $patch);
    };

    $enFiles = glob(lang_path('en/*.php')) ?: [];
    $missing = [];

    foreach ($enFiles as $enFile) {
        $filename = basename($enFile);
        /** @var array<string, mixed> $en */
        $en = require $enFile;
        $enKeys = $flatten($en);

        foreach (['bn', 'ar'] as $locale) {
            $merged = $mergeLocale($locale, $filename);
            foreach ($enKeys as $dotKey) {
                if (! \Illuminate\Support\Arr::has($merged, $dotKey)) {
                    $missing[] = "{$locale}/{$filename}: {$dotKey}";
                }
            }
        }
    }

    expect($missing)->toBe([]);
});