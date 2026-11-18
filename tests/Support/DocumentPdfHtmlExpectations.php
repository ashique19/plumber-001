<?php

namespace Tests\Support;

use ArPHP\I18N\Arabic;

final class DocumentPdfHtmlExpectations
{
    public static function shapedArabicSubstring(string $logical): string
    {
        $arabic = new Arabic;

        return $arabic->utf8Glyphs($logical, max(120, mb_strlen($logical)), false, false);
    }
}