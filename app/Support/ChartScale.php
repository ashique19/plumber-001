<?php

namespace App\Support;

/**
 * Safe min/max helpers for chart scaling when data may be empty
 * (PHP 8+ max() with a single scalar throws TypeError).
 */
final class ChartScale
{
    /**
     * @param  list<float|int>  $values
     */
    public static function floorMax(float|int $floor, array $values): float|int
    {
        if ($values === []) {
            return $floor;
        }

        return max($floor, max($values));
    }
}
