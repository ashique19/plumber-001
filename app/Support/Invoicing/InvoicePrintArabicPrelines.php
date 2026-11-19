<?php

namespace App\Support\Invoicing;

/**
 * Server-side Arabic line breaks for HTML invoice print (browser view), matching PDF rules.
 */
final class InvoicePrintArabicPrelines
{
    /** PDF / dompdf overlay budget (see DocumentPdfArabicLineWrap). */
    public const AMOUNT_WORDS_PDF_CHARS_PER_LINE = 58;

    /**
     * Narrower budget for on-screen HTML print (390px embed + 50/50 words columns).
     */
    public const AMOUNT_WORDS_HTML_CHARS_PER_LINE = 32;

    public const AMOUNT_WORDS_MAX_LINES = 2;

    /**
     * @return array{lines: list<string>, compact: bool}
     */
    public static function amountWordsBlock(string $text): array
    {
        $result = ArabicLineBreaker::breakForPdfBlock(
            $text,
            self::AMOUNT_WORDS_HTML_CHARS_PER_LINE,
            self::AMOUNT_WORDS_MAX_LINES,
        );
        $result['lines'] = ArabicLineBreaker::avoidTrailingWa($result['lines']);

        return $result;
    }

    /**
     * @return array{lines: list<string>, compact: bool}
     */
    public static function amountWordsBlockForPdf(string $text): array
    {
        return ArabicLineBreaker::breakForPdfBlock(
            $text,
            self::AMOUNT_WORDS_PDF_CHARS_PER_LINE,
            self::AMOUNT_WORDS_MAX_LINES,
        );
    }
}