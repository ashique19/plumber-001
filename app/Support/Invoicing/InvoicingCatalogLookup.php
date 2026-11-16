<?php

namespace App\Support\Invoicing;

use App\Models\Product;
use Illuminate\Support\Collection;

final class InvoicingCatalogLookup
{
    /**
     * @return list<array{id: int, label: string, meta: string, search: string}>
     */
    public static function pickerRows(string $search = '', int $limit = 40): array
    {
        $query = Product::query()
            ->visibleInCatalog()
            ->orderBy('sort_order')
            ->orderBy('name_en');

        $term = mb_strtolower(trim($search));
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term): void {
                $q->whereRaw('LOWER(COALESCE(sku, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(name_en) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(name_ar, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(name_bn, "")) LIKE ?', [$like]);
            });
        }

        return $query->with(['unit', 'size'])->limit($limit)
            ->get()
            ->map(function (Product $product): array {
                $label = $product->displayName(app()->getLocale());
                $searchBlob = mb_strtolower(implode(' ', array_filter([
                    $product->sku,
                    $product->name_en,
                    $product->name_ar,
                    $product->name_bn,
                ])));

                return [
                    'id' => $product->id,
                    'label' => $label,
                    'meta' => __('invoices.catalog_product_meta', [
                        'type' => $product->unitPickerLabel(app()->getLocale()),
                        'price' => number_format((float) $product->price_ex_vat, 2),
                        'vat' => number_format((float) $product->vat_rate, 0),
                    ]),
                    'search' => $searchBlob,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Product>
     */
    public static function productsForAddLine(string $search, int $limit = 15): Collection
    {
        $query = Product::query()->visibleInCatalog();

        $term = trim($search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term): void {
                $q->where('sku', 'like', $like)
                    ->orWhere('name_en', 'like', $like)
                    ->orWhere('name_ar', 'like', $like);
            });
        }

        return $query->orderBy('name_en')->limit($limit)->get();
    }
}