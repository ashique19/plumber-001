<?php

namespace App\Support\Invoicing;

final class ArabicLineBreaker
{
    /**
     * @return list<string>
     */
    public static function breakLines(string $text, int $maxCharsPerLine): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [];
        }

        $maxCharsPerLine = max(8, $maxCharsPerLine);
        if (mb_strlen($text) <= $maxCharsPerLine) {
            return [$text];
        }

        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            if (mb_strlen($word) > $maxCharsPerLine) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                foreach (self::hardChunk($word, $maxCharsPerLine) as $chunk) {
                    $lines[] = $chunk;
                }

                continue;
            }

            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) > $maxCharsPerLine && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$text] : $lines;
    }

    /**
     * @return list<string>
     */
    private static function hardChunk(string $word, int $maxCharsPerLine): array
    {
        $chunks = [];
        $length = mb_strlen($word);
        for ($offset = 0; $offset < $length; $offset += $maxCharsPerLine) {
            $chunks[] = mb_substr($word, $offset, $maxCharsPerLine);
        }

        return $chunks;
    }
}