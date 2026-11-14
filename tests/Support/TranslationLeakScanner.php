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
}