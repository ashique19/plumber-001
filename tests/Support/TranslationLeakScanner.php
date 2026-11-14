<?php

namespace Tests\Support;

use PHPUnit\Framework\Assert;

final class TranslationLeakScanner
{
    /** @var list<string> */
    private const KEY_PREFIXES = [
        'business.',
        'invoices.',
        'catalog.',
        'inventory.',
        'dashboard.',
        'pos.',
        'reports.',
        'customers.',
        'team.',
        'operations.',
    ];

    public static function assertHtmlHasNoLeakedKeys(string $html, string $context): void
    {
        $pattern = '/\b((?:'.implode('|', array_map(
            static fn (string $p): string => preg_quote(rtrim($p, '.'), '/'),
            self::KEY_PREFIXES,
        )).')(?:\.[a-z][a-z0-9_]*){2,})\b/i';

        if (! preg_match_all($pattern, $html, $matches)) {
            return;
        }

        $leaks = [];
        foreach (array_unique($matches[1]) as $key) {
            $translated = __($key);
            if ($translated === $key || str_contains($translated, $key.'.')) {
                $leaks[] = $key;
            }
        }

        Assert::assertSame(
            [],
            $leaks,
            "{$context}: untranslated keys in HTML: ".implode(', ', $leaks)
        );
    }

    /**
     * Spot-check English copy that should never appear on localized business settings pages.
     */
    public static function assertBusinessPageHasNoEnglishLabels(string $html, string $locale): void
    {
        if ($locale === 'en') {
            return;
        }

        $forbidden = [
            'Business settings',
            'Brand, legal, contact, and hours',
            'Legal & tax',
            'Display name (English)',
            'Display name (Arabic)',
            'Display name (Bengali',
            'Tagline (English)',
            'Tagline (Arabic)',
            'Brand color',
            'Legal name (English)',
            'Legal name (Arabic)',
            'VAT number',
            'Commercial registration (CR)',
            'Invoice address (English)',
            'Invoice address (Arabic)',
            'National address (Saudi)',
            'Building no.',
            'Postal code (5 digits)',
            'Additional no.',
            'These details must match your Z-Heart',
            'Save section',
        ];

        $leaks = [];
        foreach ($forbidden as $phrase) {
            if (str_contains($html, $phrase)) {
                $leaks[] = $phrase;
            }
        }

        Assert::assertSame(
            [],
            $leaks,
            "{$locale} business settings page shows English labels: ".implode(', ', $leaks)
        );
    }
}