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

        if ($lines === []) {
            return [$text];
        }

        return self::balanceOrphanLastLine($lines);
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    public static function balanceOrphanLastLine(array $lines): array
    {
        if (count($lines) < 2) {
            return $lines;
        }

        $last = array_pop($lines);
        while (self::wordCount($last) === 1 && $lines !== []) {
            $previous = array_pop($lines);
            $previousWords = self::words($previous);
            if (count($previousWords) < 2) {
                $lines[] = $previous;

                break;
            }

            $moved = array_pop($previousWords);
            $last = $moved.' '.$last;
            if ($previousWords !== []) {
                $lines[] = implode(' ', $previousWords);
            }
        }

        $lines[] = $last;

        return $lines;
    }

    /**
     * Prefer at most $maxLines after optional compact reflow (wider per-line budget).
     *
     * @return array{lines: list<string>, compact: bool}
     */
    public static function breakForPdfBlock(string $text, int $maxCharsPerLine, int $maxLines = 2): array
    {
        $lines = self::breakLines($text, $maxCharsPerLine);
        $compact = false;

        if (count($lines) > $maxLines) {
            $expanded = self::breakLines($text, (int) round($maxCharsPerLine * 1.12));
            if (count($expanded) <= $maxLines) {
                $lines = $expanded;
                $compact = true;
            }
        }

        return ['lines' => $lines, 'compact' => $compact];
    }

    private static function wordCount(string $line): int
    {
        return count(self::words($line));
    }

    /**
     * @return list<string>
     */
    private static function words(string $line): array
    {
        $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');

        return $line === '' ? [] : (preg_split('/\s+/u', $line) ?: []);
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