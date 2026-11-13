#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Single-client plumber catalog SQL fragment (one business scope).
 * Appends to docs/sql/plumber-catalog.sql for manual installs.
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\ProductItemType;
use Database\Seeders\PlumberCatalogSeeder;

(new PlumberCatalogSeeder)->run();

$out = <<<'SQL'
-- Plumber service catalog (single-client). Safe to re-run with INSERT patterns from seeders.
-- Generate with: php scripts/generate-plumber-job-catalog-sql.php

SQL;

$products = \App\Models\Product::query()->orderBy('sku')->get();
foreach ($products as $product) {
    $type = $product->item_type?->value ?? ProductItemType::Service->value;
    $out .= sprintf(
        "-- %s (%s) price_ex_vat=%s\n",
        $product->sku,
        $type,
        $product->price_ex_vat,
    );
}

$path = base_path('docs/sql/plumber-catalog.sql');
file_put_contents($path, $out);
echo "Wrote {$path}\n";
