<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\Schema;

final class ProductStorefrontVisibility
{
    private static ?bool $columnsExist = null;

    public static function columnsExist(): bool
    {
        if (self::$columnsExist === null) {
            self::$columnsExist = Schema::hasColumn('products', 'show_on_homepage')
                && Schema::hasColumn('products', 'show_on_menu');
        }

        return self::$columnsExist;
    }

    public static function defaultsForNewProduct(): array
    {
        if (! self::columnsExist()) {
            return [];
        }

        return [
            'show_on_homepage' => false,
            'show_on_menu' => true,
        ];
    }
}
