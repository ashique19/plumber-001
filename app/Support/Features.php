<?php

namespace App\Support;

final class Features
{
    public static function loyaltyEnabled(): bool
    {
        return (bool) config('features.loyalty', false);
    }
}