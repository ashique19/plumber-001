<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Toast copy in Livewire must resolve in en, bn, and ar (not echo the raw key).
 */
it('resolves Livewire toast translation keys in en, bn, and ar', function () {
    $pattern = '/__\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,|\))/';

    $keys = collect(File::allFiles(app_path('Livewire')))
        ->flatMap(function (SplFileInfo $file) use ($pattern) {
            $contents = File::get($file->getPathname());
            preg_match_all($pattern, $contents, $matches);

            return $matches[1] ?? [];
        })
        ->unique()
        ->sort()
        ->values();

    expect($keys)->not->toBeEmpty();

    $loadLocale = function (string $locale, string $filename): array {
        $enPath = lang_path("en/{$filename}");
        if (! is_file($enPath)) {
            return [];
        }

        /** @var array<string, mixed> $en */
        $en = require $enPath;
        $localePath = lang_path("{$locale}/{$filename}");

        if (! is_file($localePath)) {
            return $en;
        }

        /** @var array<string, mixed> $patch */
        $patch = require $localePath;

        return array_replace_recursive($en, $patch);
    };

    $missing = [];

    foreach ($keys as $dotKey) {
        if (! str_contains($dotKey, '.')) {
            continue;
        }

        [$file, $rest] = explode('.', $dotKey, 2);

        foreach (['en', 'bn', 'ar'] as $locale) {
            $merged = $loadLocale($locale, "{$file}.php");

            if (! Arr::has($merged, $rest)) {
                $missing[] = "{$locale}: {$dotKey}";
            }
        }
    }

    expect($missing)->toBe([]);
});