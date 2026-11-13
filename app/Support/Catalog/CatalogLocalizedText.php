<?php

namespace App\Support\Catalog;

final class CatalogLocalizedText
{
    public static function pick(?string $en, ?string $ar, ?string $bn, ?string $locale): string
    {
        $en = trim((string) $en);
        $ar = trim((string) $ar);
        $bn = trim((string) $bn);

        return match ($locale) {
            'ar' => $ar !== '' ? $ar : $en,
            'bn' => $bn !== '' ? $bn : $en,
            default => $en,
        };
    }
}
