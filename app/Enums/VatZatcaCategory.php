<?php

namespace App\Enums;

enum VatZatcaCategory: string
{
    case Standard = 'S';
    case Zero = 'Z';
    case Exempt = 'E';
    case Outside = 'O';

    public function vatRateDefault(): float
    {
        return match ($this) {
            self::Standard => 15.0,
            self::Zero, self::Exempt, self::Outside => 0.0,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}