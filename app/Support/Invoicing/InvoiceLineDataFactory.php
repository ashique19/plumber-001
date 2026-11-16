<?php

namespace App\Support\Invoicing;

use App\Data\InvoiceLineData;
use App\Models\Product;

final class InvoiceLineDataFactory
{
    public static function fromPayloadRow(array $line, ?Product $product = null): InvoiceLineData
    {
        $nameAr = trim((string) ($line['name_ar'] ?? ''));

        $unitCode = trim((string) ($line['unit_code'] ?? ''));
        if ($unitCode === '' && $product !== null) {
            $unitCode = $product->ublUnitCode();
        }

        $vatCategory = trim((string) ($line['vat_zatca_category'] ?? ''));
        if ($vatCategory === '' && $product !== null) {
            $vatCategory = $product->vatZatcaCategory()->value;
        }

        return new InvoiceLineData(
            name: (string) ($line['name'] ?? ''),
            quantity: (float) ($line['quantity'] ?? 0),
            unitPriceExVat: (float) ($line['unit_price_ex_vat'] ?? 0),
            vatRate: (float) ($line['vat_rate'] ?? 15),
            discountExVat: (float) ($line['discount_ex_vat'] ?? 0),
            nameAr: $nameAr !== '' ? $nameAr : null,
            unitCode: $unitCode !== '' ? $unitCode : null,
            vatZatcaCategory: $vatCategory !== '' ? $vatCategory : null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<InvoiceLineData>
     */
    public static function fromPayloadLines(array $raw): array
    {
        $productIds = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $id = (int) ($line['product_id'] ?? 0);
            if ($id > 0) {
                $productIds[$id] = $id;
            }
        }

        $products = $productIds === []
            ? collect()
            : Product::query()->with('unit')->whereIn('id', array_values($productIds))->get()->keyBy('id');

        return array_map(
            static function (array $line) use ($products): InvoiceLineData {
                $productId = (int) ($line['product_id'] ?? 0);
                $product = $productId > 0 ? $products->get($productId) : null;

                return self::fromPayloadRow($line, $product);
            },
            $raw,
        );
    }
}