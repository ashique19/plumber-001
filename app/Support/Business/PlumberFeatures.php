<?php

namespace App\Support\Business;

final class PlumberFeatures
{
    public static function loyaltyEnabled(): bool
    {
        return (bool) config('plumber.features.loyalty', false);
    }

    public static function modifiersEnabled(): bool
    {
        return (bool) config('plumber.features.modifiers', false);
    }
}