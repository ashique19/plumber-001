<?php

namespace App\Support\Customers;

use Illuminate\Support\Facades\Schema;

/**
 * Whether the live database has ZATCA identity columns (migration or manual SQL).
 */
final class CustomerSchema
{
    private static ?bool $identityColumns = null;

    public static function hasIdentityColumns(): bool
    {
        if (self::$identityColumns !== null) {
            return self::$identityColumns;
        }

        self::$identityColumns = Schema::hasColumn('customers', 'legal_name_ar')
            && Schema::hasColumn('customers', 'city');

        return self::$identityColumns;
    }

    public static function resetCache(): void
    {
        self::$identityColumns = null;
    }
}
