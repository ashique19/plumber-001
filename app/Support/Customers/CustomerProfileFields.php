<?php

namespace App\Support\Customers;

use App\Models\Customer;

final class CustomerProfileFields
{
    public static function crNumber(Customer $customer): ?string
    {
        $altType = strtoupper(trim((string) ($customer->alt_id_type ?? '')));
        $altNumber = trim((string) ($customer->alt_id_number ?? ''));
        if ($altType === 'CRN' && $altNumber !== '') {
            return $altNumber;
        }

        $cr = trim((string) data_get($customer->profile, 'cr_number', ''));

        return $cr !== '' ? $cr : null;
    }

    public static function city(Customer $customer): ?string
    {
        $city = trim((string) ($customer->city ?? ''));
        if ($city !== '') {
            return $city;
        }

        $city = trim((string) data_get($customer->profile, 'national_address.city', ''));

        return $city !== '' ? $city : null;
    }
}