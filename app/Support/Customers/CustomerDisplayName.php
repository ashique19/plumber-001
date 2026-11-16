<?php

namespace App\Support\Customers;

use App\Models\Customer;

final class CustomerDisplayName
{
    public static function for(Customer $customer, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $ar = trim((string) ($customer->legal_name_ar ?? data_get($customer->profile, 'name_ar', '')));
        $en = trim((string) ($customer->legal_name_en ?? $customer->name ?? ''));

        if ($locale === 'ar' && $ar !== '') {
            return $ar;
        }

        if ($en !== '') {
            return $en;
        }

        if ($ar !== '') {
            return $ar;
        }

        return trim((string) ($customer->phone ?? '')) ?: __('customers.guest_label');
    }

    /**
     * @return array{en: string|null, ar: string|null, display: string}
     */
    public static function printPair(Customer $customer): array
    {
        $ar = trim((string) ($customer->legal_name_ar ?? data_get($customer->profile, 'name_ar', '')));
        $en = trim((string) ($customer->legal_name_en ?? $customer->name ?? ''));

        return [
            'en' => $en !== '' ? $en : null,
            'ar' => $ar !== '' ? $ar : null,
            'display' => self::for($customer),
        ];
    }
}