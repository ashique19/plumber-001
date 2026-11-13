<?php

namespace Database\Seeders;

use App\Enums\ProductItemType;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Storefront\PlumberStorefrontImagery;
use Illuminate\Database\Seeder;

class PlumberCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $emergency = ProductCategory::query()->firstOrCreate(
            ['name_en' => 'Emergency'],
            ['name_ar' => 'طوارئ', 'icon_key' => 'wrench'],
        );

        $maintenance = ProductCategory::query()->firstOrCreate(
            ['name_en' => 'Maintenance'],
            ['name_ar' => 'صيانة', 'icon_key' => 'wrench'],
        );

        $install = ProductCategory::query()->firstOrCreate(
            ['name_en' => 'Installation'],
            ['name_ar' => 'تركيب', 'icon_key' => 'wrench'],
        );

        $partsCat = ProductCategory::query()->firstOrCreate(
            ['name_en' => 'Parts & materials'],
            ['name_ar' => 'قطع ومواد', 'icon_key' => 'wrench'],
        );

        $services = [
            ['sku' => 'SRV-LEAK', 'cat' => $emergency, 'name_en' => 'Leak repair visit', 'name_ar' => 'زيارة إصلاح تسرب', 'price' => 180],
            ['sku' => 'SRV-BURST', 'cat' => $emergency, 'name_en' => 'Burst pipe isolation', 'name_ar' => 'عزل أنبوب منفجر', 'price' => 250],
            ['sku' => 'SRV-DRAIN', 'cat' => $maintenance, 'name_en' => 'Drain clearing', 'name_ar' => 'تسليك مجاري', 'price' => 140],
            ['sku' => 'SRV-FIX', 'cat' => $maintenance, 'name_en' => 'Fixture tune-up', 'name_ar' => 'صيانة تجهيزات', 'price' => 95],
            ['sku' => 'SRV-HEAT', 'cat' => $install, 'name_en' => 'Water heater install', 'name_ar' => 'تركيب سخان مياه', 'price' => 420],
            ['sku' => 'SRV-FILT', 'cat' => $install, 'name_en' => 'Filtration system install', 'name_ar' => 'تركيب نظام تنقية', 'price' => 320],
        ];

        foreach ($services as $row) {
            Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'product_category_id' => $row['cat']->id,
                    'item_type' => ProductItemType::Service,
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'price_ex_vat' => $row['price'],
                    'vat_rate' => 15,
                    'image_url' => PlumberStorefrontImagery::pathForSku($row['sku']),
                    'is_active' => true,
                ]
            );
        }

        $parts = [
            ['sku' => 'PTFE', 'name_en' => 'PTFE tape roll', 'name_ar' => 'شريط تفلون', 'price' => 8],
            ['sku' => 'WASH-KIT', 'name_en' => 'Rubber washer kit', 'name_ar' => 'طقم حشوات مطاطية', 'price' => 15],
            ['sku' => 'ELBOW-15', 'name_en' => '15mm brass elbow', 'name_ar' => 'كوع نحاس ١٥ مم', 'price' => 12],
            ['sku' => 'VALVE-ANG', 'name_en' => 'Angle stop valve', 'name_ar' => 'صمام زاوية', 'price' => 45],
            ['sku' => 'PTRAP', 'name_en' => 'P-trap assembly', 'name_ar' => 'مصيدة P', 'price' => 38],
            ['sku' => 'SEALANT', 'name_en' => 'Plumber’s putty (tub)', 'name_ar' => 'معجون سباكة', 'price' => 18],
        ];

        foreach ($parts as $row) {
            Product::query()->updateOrCreate(
                ['sku' => $row['sku']],
                [
                    'product_category_id' => $partsCat->id,
                    'item_type' => ProductItemType::Part,
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'price_ex_vat' => $row['price'],
                    'vat_rate' => 15,
                    'is_active' => true,
                ]
            );
        }

        Branch::query()->firstOrCreate(
            ['code' => 'ruh-main'],
            ['name' => 'Riyadh Service Center', 'name_ar' => 'مركز الرياض'],
        );
    }
}
