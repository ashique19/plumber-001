<?php

namespace App\Support\Invoicing;

/**
 * Server-side English line breaks for HTML invoice print (browser), matching Arabic rules.
 */
final class InvoicePrintEnglishPrelines
{
    public const AMOUNT_WORDS_HTML_CHARS_PER_LINE = 36;

    public const AMOUNT_WORDS_PDF_CHARS_PER_LINE = 58;

    public const AMOUNT_WORDS_MAX_LINES = 2;

    /**
     * @return array{lines: list<string>, compact: bool, single_line: bool}
     */
    public static function amountWordsBlock(string $text): array
    {
        $result = ArabicLineBreaker::breakForPdfBlock(
            $text,
            self::AMOUNT_WORDS_HTML_CHARS_PER_LINE,
            self::AMOUNT_WORDS_MAX_LINES,
        );
        $result['lines'] = self::polishLines($result['lines']);

        return [
            'lines' => $result['lines'],
            'compact' => $result['compact'],
            'single_line' => count($result['lines']) === 1,
        ];
    }

    /**
     * @return array{lines: list<string>, compact: bool, single_line: bool}
     */
    public static function amountWordsBlockForPdf(string $text): array
    {
        $result = ArabicLineBreaker::breakForPdfBlock(
            $text,
            self::AMOUNT_WORDS_PDF_CHARS_PER_LINE,
            self::AMOUNT_WORDS_MAX_LINES,
        );

        return [
            'lines' => $result['lines'],
            'compact' => $result['compact'],
            'single_line' => count($result['lines']) === 1,
        ];
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function polishLines(array $lines): array
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? ''),
            $lines,
        ), static fn (string $line): bool => $line !== ''));

        if ($lines === []) {
            return [];
        }

        $lines = ArabicLineBreaker::balanceOrphanLastLine($lines);

        if (count($lines) < 2) {
            return $lines;
        }

        $last = (string) end($lines);
        $lastWords = self::words($last);
        $previous = $lines[count($lines) - 2];
        $previousWords = self::words($previous);

        if (count($lastWords) === 1 && strtolower($lastWords[0]) === 'only') {
            array_pop($lines);
            array_pop($lines);

            return ArabicLineBreaker::balanceOrphanLastLine(array_merge($lines, [trim($previous.' '.$last)]));
        }

        if (count($lastWords) === 2 && strtolower($lastWords[0]) === 'halalas' && count($previousWords) >= 2) {
            $moved = array_pop($previousWords);

            return [
                implode(' ', $previousWords),
                trim($moved.' '.$last),
            ];
        }

        if (count($lastWords) === 1) {
            return ArabicLineBreaker::balanceOrphanLastLine($lines);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function words(string $line): array
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');

        return $line === '' ? [] : (preg_split('/\s+/u', $line) ?: []);
    }
}