<?php

namespace App\Support\Invoicing;

use App\Models\Product;

final class DocumentCatalogLineMerge
{
    public static function variantKeyForProduct(Product $product): string
    {
        return self::variantKey(
            (int) $product->id,
            $product->product_unit_id !== null ? (int) $product->product_unit_id : null,
            $product->product_size_id !== null ? (int) $product->product_size_id : null,
        );
    }

    public static function variantKeyForInvoicingLine(array $line): ?string
    {
        if (! filled($line['product_id'] ?? null)) {
            return null;
        }

        $unitId = $line['product_unit_id'] ?? null;
        $sizeId = $line['product_size_id'] ?? null;

        return self::variantKey(
            (int) $line['product_id'],
            is_numeric($unitId) ? (int) $unitId : null,
            is_numeric($sizeId) ? (int) $sizeId : null,
        );
    }

    public static function variantKeyForPurchaseLine(array $line): ?string
    {
        if (! filled($line['product_id'] ?? null)) {
            return null;
        }

        return (string) (int) $line['product_id'];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function findInvoicingLineIndex(array $lines, Product $product): ?int
    {
        $target = self::variantKeyForProduct($product);

        foreach ($lines as $index => $line) {
            if (self::variantKeyForInvoicingLine($line) === $target) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public static function findPurchaseLineIndex(array $lines, Product $product): ?int
    {
        $target = (string) $product->id;

        foreach ($lines as $index => $line) {
            if (self::variantKeyForPurchaseLine($line) === $target) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array{capped: bool, quantity: string}
     */
    public static function bumpLineQuantity(array &$lines, int $index, float $delta = 1.0): array
    {
        if (! isset($lines[$index])) {
            return ['capped' => false, 'quantity' => '1'];
        }

        $current = max(0.001, (float) ($lines[$index]['quantity'] ?? 1));
        $next = round($current + $delta, 3);
        $capped = false;

        if (! empty($lines[$index]['track_stock']) && filled($lines[$index]['stock_quantity'] ?? null)) {
            $max = (float) $lines[$index]['stock_quantity'];
            if ($max > 0 && $next > $max) {
                $next = $max;
                $capped = true;
            }
        }

        $lines[$index]['quantity'] = self::formatQuantity($next);

        return ['capped' => $capped, 'quantity' => $lines[$index]['quantity']];
    }

    public static function formatQuantity(float $qty): string
    {
        $formatted = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function variantKey(int $productId, ?int $unitId, ?int $sizeId): string
    {
        return $productId.':'.($unitId ?? 0).':'.($sizeId ?? 0);
    }
}