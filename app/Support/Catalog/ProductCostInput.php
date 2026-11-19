<?php

namespace App\Support\Catalog;

final class ProductCostInput
{
    public static function parseOptional(null|string|float|int $input): ?float
    {
        if ($input === null) {
            return null;
        }

        if (is_string($input)) {
            $trimmed = trim($input);
            if ($trimmed === '') {
                return null;
            }

            return round((float) $trimmed, 2);
        }

        return round((float) $input, 2);
    }
}