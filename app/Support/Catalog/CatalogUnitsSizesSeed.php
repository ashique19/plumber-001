<?php

namespace App\Support\Catalog;

final class CatalogUnitsSizesSeed
{
    /**
     * @return list<array{code: string, name_en: string, name_ar: string, name_bn: string, ubl: string}>
     */
    public static function units(): array
    {
        return [
            ['code' => 'PCS', 'name_en' => 'Piece', 'name_ar' => 'قطعة', 'name_bn' => 'পিস', 'ubl' => 'PCE'],
            ['code' => 'M', 'name_en' => 'Meter', 'name_ar' => 'متر', 'name_bn' => 'মিটার', 'ubl' => 'MTR'],
            ['code' => 'BOX', 'name_en' => 'Box', 'name_ar' => 'صندوق', 'name_bn' => 'বাক্স', 'ubl' => 'BX'],
            ['code' => 'ROLL', 'name_en' => 'Roll', 'name_ar' => 'لفة', 'name_bn' => 'রোল', 'ubl' => 'RO'],
            ['code' => 'KG', 'name_en' => 'Kilogram', 'name_ar' => 'كيلوغرام', 'name_bn' => 'কিলোগ্রাম', 'ubl' => 'KGM'],
            ['code' => 'L', 'name_en' => 'Liter', 'name_ar' => 'لتر', 'name_bn' => 'লিটার', 'ubl' => 'LTR'],
            ['code' => 'SET', 'name_en' => 'Set', 'name_ar' => 'مجموعة', 'name_bn' => 'সেট', 'ubl' => 'SET'],
            ['code' => 'HR', 'name_en' => 'Hour', 'name_ar' => 'ساعة', 'name_bn' => 'ঘণ্টা', 'ubl' => 'HUR'],
            ['code' => 'JOB', 'name_en' => 'Job', 'name_ar' => 'مهمة', 'name_bn' => 'কাজ', 'ubl' => 'E48'],
        ];
    }

    /**
     * @return list<array{name: string, measurement_type: string|null}>
     */
    public static function sizes(): array
    {
        $names = [
            '1/2 Inch',
            '3/4 Inch',
            '1 Inch',
            '1 1/4 Inch',
            '1 1/2 Inch',
            '2 Inch',
            '3 Inch',
            '4 Inch',
        ];

        return array_map(
            static fn (string $name): array => ['name' => $name, 'measurement_type' => 'Diameter'],
            $names,
        );
    }
}