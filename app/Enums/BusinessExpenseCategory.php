<?php

namespace App\Enums;

enum BusinessExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Fuel = 'fuel';
    case Salaries = 'salaries';
    case Supplies = 'supplies';
    case Marketing = 'marketing';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}