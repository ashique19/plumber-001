<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Schema;

final class ProductCategorySchema
{
    public static function tableReady(): bool
    {
        return Schema::hasTable('product_categories');
    }

    public static function hasNameEnColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'name_en');
    }

    public static function hasLegacyNameColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'name');
    }

    public static function hasNameArColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'name_ar');
    }

    public static function hasNameBnColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'name_bn');
    }

    public static function hasDescriptionEnColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'description_en');
    }

    public static function hasDescriptionArColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'description_ar');
    }

    public static function hasDescriptionBnColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'description_bn');
    }

    public static function hasIconKeyColumn(): bool
    {
        return self::tableReady() && Schema::hasColumn('product_categories', 'icon_key');
    }

    public static function nameOrderColumn(): string
    {
        if (self::hasNameEnColumn()) {
            return 'name_en';
        }

        if (self::hasLegacyNameColumn()) {
            return 'name';
        }

        return 'id';
    }

    /**
     * @param  array{
     *     categoryNameEn: string,
     *     categoryNameAr?: ?string,
     *     categoryNameBn?: ?string,
     *     categoryDescriptionEn?: ?string,
     *     categoryDescriptionAr?: ?string,
     *     categoryDescriptionBn?: ?string,
     *     categoryIconKey?: ?string,
     *     categoryActive: bool,
     * }  $validated
     * @return array<string, mixed>
     */
    public static function mutationPayload(array $validated): array
    {
        $payload = [];

        $nameEn = trim($validated['categoryNameEn']);
        if (self::hasNameEnColumn()) {
            $payload['name_en'] = $nameEn;
        } elseif (self::hasLegacyNameColumn()) {
            $payload['name'] = $nameEn;
        }

        if (self::hasNameArColumn()) {
            $ar = trim((string) ($validated['categoryNameAr'] ?? ''));
            $payload['name_ar'] = $ar !== '' ? $ar : null;
        }

        if (self::hasNameBnColumn()) {
            $bn = trim((string) ($validated['categoryNameBn'] ?? ''));
            $payload['name_bn'] = $bn !== '' ? $bn : null;
        }

        if (self::hasDescriptionEnColumn()) {
            $en = trim((string) ($validated['categoryDescriptionEn'] ?? ''));
            $payload['description_en'] = $en !== '' ? $en : null;
        }

        if (self::hasDescriptionArColumn()) {
            $ar = trim((string) ($validated['categoryDescriptionAr'] ?? ''));
            $payload['description_ar'] = $ar !== '' ? $ar : null;
        }

        if (self::hasDescriptionBnColumn()) {
            $bn = trim((string) ($validated['categoryDescriptionBn'] ?? ''));
            $payload['description_bn'] = $bn !== '' ? $bn : null;
        }

        if (self::hasIconKeyColumn()) {
            $icon = trim((string) ($validated['categoryIconKey'] ?? ''));
            $payload['icon_key'] = $icon !== '' ? $icon : null;
        }

        $payload['is_active'] = (bool) $validated['categoryActive'];

        return $payload;
    }
}