<?php

namespace App\Support\Invoicing;

final class DocumentPdfEnLineFit
{
    /**
     * Inline style for a single Latin line in Dompdf: nowrap, shrink font when text is long.
     */
    public static function inlineStyle(string $text, float $maxMm, float $basePt): string
    {
        $pt = self::fitPointSize($text, $maxMm, $basePt);

        return 'white-space:nowrap;font-size:'.self::formatPt($pt).';line-height:1.3;display:block;';
    }

    public static function fitPointSize(string $text, float $maxMm, float $basePt, float $minPt = 5.5): float
    {
        $len = mb_strlen(trim($text));
        if ($len === 0) {
            return $basePt;
        }

        $pt = $basePt;
        while ($pt > $minPt && self::estimateWidthMm($len, $pt) > $maxMm) {
            $pt -= 0.25;
        }

        return max($minPt, $pt);
    }

    private static function estimateWidthMm(int $charCount, float $pt): float
    {
        return $charCount * $pt * 0.19;
    }

    private static function formatPt(float $pt): string
    {
        $formatted = rtrim(rtrim(number_format($pt, 2, '.', ''), '0'), '.');

        return $formatted.'pt';
    }
}