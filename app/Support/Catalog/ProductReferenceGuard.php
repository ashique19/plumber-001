<?php

namespace App\Support\Catalog;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseLine;

final class ProductReferenceGuard
{
    public static function isReferenced(Product $product): bool
    {
        if (PurchaseLine::query()->where('product_id', $product->id)->exists()) {
            return true;
        }

        $id = (string) $product->id;

        return Invoice::query()
            ->where('status', 'issued')
            ->where(function ($q) use ($id): void {
                $q->where('payload->lines', 'like', '%"product_id":'.$id.'%')
                    ->orWhere('payload->lines', 'like', '%"product_id": '.$id.'%');
            })
            ->exists();
    }
}