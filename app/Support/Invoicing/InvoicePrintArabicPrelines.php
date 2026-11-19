<?php

namespace App\Support\Invoicing;

/**
 * Server-side Arabic line breaks for HTML invoice print (browser view), matching PDF rules.
 */
final class InvoicePrintArabicPrelines
{
    public const AMOUNT_WORDS_CHARS_PER_LINE = 58;

    public const AMOUNT_WORDS_MAX_LINES = 2;

    /**
     * @return array{lines: list<string>, compact: bool}
     */
    public static function amountWordsBlock(string $text): array
    {
        return ArabicLineBreaker::breakForPdfBlock(
            $text,
            self::AMOUNT_WORDS_CHARS_PER_LINE,
            self::AMOUNT_WORDS_MAX_LINES,
        );
    }
}