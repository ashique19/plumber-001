<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Schema;

final class CatalogI18n
{
    public static function columnsExist(): bool
    {
        return Schema::hasColumn('products', 'name_bn')
            && Schema::hasColumn('products', 'description_en')
            && Schema::hasColumn('product_categories', 'name_bn')
            && Schema::hasColumn('product_categories', 'description_en');
    }

    public static function localizedName(object $model, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        if (! self::columnsExist()) {
            if ($locale === 'ar') {
                return trim((string) ($model->name_ar ?? '')) ?: (string) $model->name_en;
            }

            return (string) $model->name_en;
        }

        return CatalogLocalizedText::pick(
            (string) $model->name_en,
            (string) ($model->name_ar ?? ''),
            (string) ($model->name_bn ?? ''),
            $locale,
        );
    }

    public static function localizedDescription(object $model, ?string $locale = null): string
    {
        if (! self::columnsExist()) {
            return '';
        }

        $locale ??= app()->getLocale();

        return CatalogLocalizedText::pick(
            (string) ($model->description_en ?? ''),
            (string) ($model->description_ar ?? ''),
            (string) ($model->description_bn ?? ''),
            $locale,
        );
    }
}
