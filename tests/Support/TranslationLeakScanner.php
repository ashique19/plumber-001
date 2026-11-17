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
        'stock.',
        'purchases.',
        'dashboard.',
        'pos.',
        'reports.',
        'customers.',
        'payments.',
        'team.',
        'operations.',
        'staff_users.',
        'print.',
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

    public static function assertLocaleHasNoEnglishSigningService(string $html, string $locale, string $context): void
    {
        if (! in_array($locale, ['bn', 'ar'], true)) {
            return;
        }

        Assert::assertFalse(
            str_contains(strtolower($html), 'signing service'),
            "{$context}: English phrase \"signing service\" must not appear for locale [{$locale}]"
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

    /**
     * Spot-check English copy that should never appear on localized catalog admin.
     */
    public static function assertCatalogPageHasNoEnglishLabels(string $html, string $locale): void
    {
        if ($locale === 'en') {
            return;
        }

        $html = self::stripNonVisibleLivewireMarkup($html);

        $forbidden = [
            'Menu & products',
            'Categories, items, modifiers',
            'Enter prices',
            'Ex-VAT',
            'VAT inclusive',
            'Stored ex-VAT',
            'Add category',
            'Add product',
            'Add modifier',
            'No categories yet',
            'No products yet',
            'No modifiers yet',
            'Edit category',
            'Edit product',
            'Edit modifier',
            'Name (English)',
            'Name (Arabic',
            'Name (Bangla)',
            'Description (English)',
            'Image path',
            'Catalog type',
            'Menu item',
            'Qty per 1 sold',
            'Product ingredients',
            'Quick-create ingredient',
            'Ingredients',
            'Icon key',
            'Show on homepage',
            'Show on menu',
            'Remove from homepage',
            'VAT %',
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
            "{$locale} catalog page shows English labels: ".implode(', ', $leaks)
        );
    }

    /** Ignore Livewire wire:* payloads and scripts so method names do not look like UI copy. */
    private static function stripNonVisibleLivewireMarkup(string $html): string
    {
        $html = preg_replace('/\swire:[a-z0-9_.-]+(?:="[^"]*")?/i', '', $html) ?? $html;
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;

        return $html;
    }
}
