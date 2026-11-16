<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductSize;
use App\Models\ProductUnit;
use App\Support\Catalog\CatalogUnitsSizesSchema;
use App\Support\Catalog\CatalogUnitsSizesSeed;
use App\Support\Catalog\ProductSkuGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogUnitsSizesSeeder extends Seeder
{
    public function run(): void
    {
        if (! CatalogUnitsSizesSchema::ready()) {
            return;
        }

        foreach (CatalogUnitsSizesSeed::units() as $row) {
            ProductUnit::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name_en' => $row['name_en'],
                    'name_ar' => $row['name_ar'],
                    'name_bn' => $row['name_bn'],
                    'ubl_unit_code' => $row['ubl'],
                    'is_active' => true,
                ],
            );
        }

        foreach (CatalogUnitsSizesSeed::sizes() as $row) {
            ProductSize::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'measurement_type' => $row['measurement_type'],
                    'is_active' => true,
                ],
            );
        }

        $pcsId = ProductUnit::query()->where('code', 'PCS')->value('id');

        Product::query()->orderBy('id')->lazy()->each(function (Product $product) use ($pcsId): void {
            $dirty = false;

            if (blank($product->name_ar) && filled($product->name_en)) {
                $product->name_ar = $product->name_en;
                $product->name_i18n_review = true;
                $dirty = true;
            }
            if (blank($product->name_bn) && filled($product->name_en)) {
                $product->name_bn = $product->name_en;
                $product->name_i18n_review = true;
                $dirty = true;
            }
            if ($pcsId && $product->product_unit_id === null) {
                $product->product_unit_id = $pcsId;
                $dirty = true;
            }
            if (blank($product->sku)) {
                $product->sku = ProductSkuGenerator::next();
                $dirty = true;
            }
            if ($product->vat_zatca_category === null || $product->vat_zatca_category === '') {
                $product->vat_zatca_category = 'S';
                $dirty = true;
            }

            if ($dirty) {
                $product->save();
            }
        });

        if (DB::getSchemaBuilder()->hasTable('product_sku_counters')) {
            $max = 0;
            Product::query()->whereNotNull('sku')->pluck('sku')->each(function (string $sku) use (&$max): void {
                if (preg_match('/^PRD-(\d+)$/i', $sku, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            });
            DB::table('product_sku_counters')->updateOrInsert(
                ['key' => 'PRD'],
                ['last_value' => $max],
            );
        }
    }
}