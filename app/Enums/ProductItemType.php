<?php

namespace App\Enums;

enum ProductItemType: string
{
    case Item = 'item';
    case Service = 'service';
    case Part = 'part';

    public function isTradesTyped(): bool
    {
        return $this === self::Service || $this === self::Part;
    }

    public function label(): string
    {
        return match ($this) {
            self::Service => __('catalog.item_type_service'),
            self::Part => __('catalog.item_type_part'),
            self::Item => __('catalog.item_type_item'),
        };
    }
}
