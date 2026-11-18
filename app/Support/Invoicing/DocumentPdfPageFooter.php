<?php

namespace App\Support\Invoicing;

use ArPHP\I18N\Arabic;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

final class DocumentPdfPageFooter
{
    public static function endDocumentCallback(): callable
    {
        return static function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics): void {
            self::paint($pageNumber, $pageCount, $canvas, $fontMetrics);
        };
    }

    private static function paint(int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics): void
    {
        $latin = $fontMetrics->getFont(DocumentPdfFonts::FAMILY_LATIN, 'normal');
        $arabic = $fontMetrics->getFont('amiripdf', 'normal') ?? $latin;
        $size = 8.0;
        $color = [0.45, 0.45, 0.45];
        $y = $canvas->get_height() - 20;

        $pageWordAr = (new Arabic)->utf8Glyphs('صفحة', 20, false, false);
        $en = 'Page '.$pageNumber.' / '.$pageCount;
        $sep = '  ·  ';
        $ar = $pageWordAr.' '.$pageNumber.' / '.$pageCount;

        $enWidth = $canvas->get_text_width($en, $latin, $size);
        $sepWidth = $canvas->get_text_width($sep, $latin, $size);
        $arWidth = $canvas->get_text_width($ar, $arabic, $size);
        $total = $enWidth + $sepWidth + $arWidth;
        $x = max(18, ($canvas->get_width() - $total) / 2);

        $latinBaseline = $fontMetrics->getFontBaseline($latin, $size);
        $arabicBaseline = $fontMetrics->getFontBaseline($arabic, $size);
        $yLatin = $y;
        $yArabic = $y + ($latinBaseline - $arabicBaseline);

        $canvas->text($x, $yLatin, $en, $latin, $size, $color);
        $canvas->text($x + $enWidth, $yLatin, $sep, $latin, $size, $color);
        $canvas->text($x + $enWidth + $sepWidth, $yArabic, $ar, $arabic, $size, $color);
    }
}