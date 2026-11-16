<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ProductSkuGenerator
{
    public static function next(): string
    {
        if (! Schema::hasTable('product_sku_counters')) {
            return self::legacyNext();
        }

        return DB::transaction(function (): string {
            $row = DB::table('product_sku_counters')->lockForUpdate()->where('key', 'PRD')->first();
            if ($row === null) {
                DB::table('product_sku_counters')->insert(['key' => 'PRD', 'last_value' => 0]);
                $next = self::bootstrapFromExisting() ?: 1;
            } else {
                $next = (int) $row->last_value + 1;
            }

            $sku = sprintf('PRD-%03d', $next);

            while (Product::query()->where('sku', $sku)->exists()) {
                $next++;
                $sku = sprintf('PRD-%03d', $next);
            }

            DB::table('product_sku_counters')->where('key', 'PRD')->update(['last_value' => $next]);

            return $sku;
        });
    }

    private static function bootstrapFromExisting(): int
    {
        $max = 0;
        Product::query()
            ->whereNotNull('sku')
            ->select('sku')
            ->lazy()
            ->each(function (Product $product) use (&$max): void {
                if (preg_match('/^PRD-(\d+)$/i', (string) $product->sku, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            });

        return $max;
    }

    private static function legacyNext(): string
    {
        $max = self::bootstrapFromExisting();

        return sprintf('PRD-%03d', $max + 1);
    }
}