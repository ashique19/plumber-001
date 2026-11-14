<?php

namespace App\Support\Invoicing;

/**
 * Amount in words for printed tax invoices (Arabic tafqit + simple English).
 */
final class SarAmountInWords
{
  /**
   * @return array{ar: string, en: string}
   */
  public static function format(float $amountIncVat): array
    {
        $halalas = (int) round($amountIncVat * 100);
        $riyals = intdiv($halalas, 100);
        $fils = $halalas % 100;

        $ar = self::arabic($riyals, $fils);
        $en = self::english($riyals, $fils);

        return ['ar' => $ar, 'en' => $en];
    }

    private static function arabic(int $riyals, int $halalas): string
    {
        $riyalWord = $riyals === 1 ? 'ريال' : 'ريال';
        $parts = [];

        if ($riyals > 0) {
            $parts[] = self::arabicNumber($riyals).' '.$riyalWord;
        }

        if ($halalas > 0) {
            $parts[] = self::arabicNumber($halalas).' '.($halalas === 1 ? 'هللة' : 'هللة');
        }

        if ($parts === []) {
            return 'صفر ريال';
        }

        return implode(' و ', $parts).' فقط';
    }

    private static function arabicNumber(int $n): string
    {
        if ($n === 0) {
            return 'صفر';
        }

        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
        $tens = ['', 'عشرة', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $teens = ['عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر', 'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تنسعة عشر'];

        if ($n < 10) {
            return $ones[$n];
        }

        if ($n < 20) {
            return $teens[$n - 10];
        }

        if ($n < 100) {
            $t = intdiv($n, 10);
            $o = $n % 10;

            return $o === 0 ? $tens[$t] : $ones[$o].' و '.$tens[$t];
        }

        if ($n < 1000) {
            $h = intdiv($n, 100);
            $rest = $n % 100;
            $hundreds = match ($h) {
                1 => 'مائة',
                2 => 'مائتان',
                default => $ones[$h].' مائة',
            };

            return $rest === 0 ? $hundreds : $hundreds.' و '.self::arabicNumber($rest);
        }

        if ($n < 1_000_000) {
            $thousands = intdiv($n, 1000);
            $rest = $n % 1000;
            $thWord = match (true) {
                $thousands === 1 => 'ألف',
                $thousands === 2 => 'ألفان',
                $thousands >= 3 && $thousands <= 10 => self::arabicNumber($thousands).' آلاف',
                default => self::arabicNumber($thousands).' ألف',
            };

            return $rest === 0 ? $thWord : $thWord.' و '.self::arabicNumber($rest);
        }

        return number_format($n).' ريال';
    }

    private static function english(int $riyals, int $halalas): string
    {
        $parts = [];

        if ($riyals > 0) {
            $parts[] = $riyals.' Saudi Riyal'.($riyals === 1 ? '' : 's');
        }

        if ($halalas > 0) {
            $parts[] = $halalas.' Halala'.($halalas === 1 ? '' : 's');
        }

        if ($parts === []) {
            return 'Zero Saudi Riyals';
        }

        return implode(' and ', $parts).' only';
    }
}