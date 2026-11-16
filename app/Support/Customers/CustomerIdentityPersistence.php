<?php

namespace App\Support\Customers;

use App\Models\Customer;

final class CustomerIdentityPersistence
{
    /**
     * @param  array<string, mixed>  $state  normalized keys (legal_name_ar, vat_registered, ...)
     */
    public static function applyToCustomer(Customer $customer, array $state): void
    {
        $vatRegistered = (bool) ($state['vat_registered'] ?? false);
        $vatDigits = preg_replace('/\D/', '', (string) ($state['vat_number'] ?? '')) ?? '';
        $country = strtoupper(trim((string) ($state['country_code'] ?? 'SA'))) ?: 'SA';

        $legalEn = trim((string) ($state['legal_name_en'] ?? ''));
        $legalAr = trim((string) ($state['legal_name_ar'] ?? ''));
        $display = $legalEn !== '' ? $legalEn : ($legalAr !== '' ? $legalAr : $customer->name);

        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];

        $altType = $vatRegistered ? null : strtoupper(trim((string) ($state['alt_id_type'] ?? '')));
        $altNumber = $vatRegistered ? null : trim((string) ($state['alt_id_number'] ?? ''));

        $customer->forceFill([
            'name' => $display,
            'legal_name_ar' => $legalAr !== '' ? $legalAr : null,
            'legal_name_en' => $legalEn !== '' ? $legalEn : null,
            'vat_registered' => $vatRegistered,
            'vat_number' => $vatRegistered && $vatDigits !== '' ? $vatDigits : null,
            'alt_id_type' => $altType !== '' && $altType !== null ? $altType : null,
            'alt_id_number' => $altNumber !== '' ? $altNumber : null,
            'country_code' => $country,
            'street_name' => trim((string) ($state['street_name'] ?? '')) ?: null,
            'city' => trim((string) ($state['city'] ?? '')) ?: null,
            'phone' => filled($state['phone'] ?? null) ? trim((string) $state['phone']) : null,
            'email' => filled($state['email'] ?? null) ? trim((string) $state['email']) : null,
            'profile' => array_merge($profile, [
                'name_ar' => $legalAr !== '' ? $legalAr : null,
                'cr_number' => $altType === 'CRN' && $altNumber !== '' ? $altNumber : data_get($profile, 'cr_number'),
                'country' => $country,
                'national_address' => array_merge($na, [
                    'building' => trim((string) ($state['addr_building'] ?? '')) ?: null,
                    'street' => trim((string) ($state['street_name'] ?? '')) ?: null,
                    'district' => trim((string) ($state['addr_district'] ?? '')) ?: null,
                    'city' => trim((string) ($state['city'] ?? '')) ?: null,
                    'postal_code' => trim((string) ($state['addr_postal'] ?? '')) ?: null,
                    'additional' => trim((string) ($state['addr_additional'] ?? '')) ?: null,
                    'country' => $country,
                ]),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function stateFromCustomer(Customer $customer): array
    {
        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];

        $vatRegistered = $customer->vat_registered ?? filled($customer->vat_number);
        $altType = $customer->alt_id_type ?? (filled(data_get($profile, 'cr_number')) ? 'CRN' : '');
        $altNumber = $customer->alt_id_number ?? (string) data_get($profile, 'cr_number', '');

        return [
            'legal_name_ar' => (string) ($customer->legal_name_ar ?? data_get($profile, 'name_ar', '')),
            'legal_name_en' => (string) ($customer->legal_name_en ?? $customer->name ?? ''),
            'vat_registered' => (bool) $vatRegistered,
            'vat_number' => (string) ($customer->vat_number ?? ''),
            'alt_id_type' => strtoupper((string) $altType),
            'alt_id_number' => (string) $altNumber,
            'phone' => (string) ($customer->phone ?? ''),
            'email' => (string) ($customer->email ?? ''),
            'country_code' => strtoupper((string) ($customer->country_code ?? data_get($profile, 'country', 'SA'))) ?: 'SA',
            'street_name' => (string) ($customer->street_name ?? $na['street'] ?? ''),
            'city' => (string) ($customer->city ?? CustomerProfileFields::city($customer) ?? ''),
            'addr_building' => (string) ($na['building'] ?? ''),
            'addr_postal' => (string) ($na['postal_code'] ?? $na['postal'] ?? ''),
            'addr_district' => (string) ($na['district'] ?? ''),
            'addr_additional' => (string) ($na['additional'] ?? ''),
        ];
    }

    /**
     * @param  object  $host
     * @param  array<string, string>  $fields
     * @return array<string, mixed>
     */
    public static function stateFromHost(object $host, array $fields): array
    {
        return [
            'legal_name_ar' => (string) ($host->{$fields['legal_name_ar']} ?? ''),
            'legal_name_en' => (string) ($host->{$fields['legal_name_en']} ?? ''),
            'vat_registered' => (bool) ($host->{$fields['vat_registered']} ?? false),
            'vat_number' => (string) ($host->{$fields['vat_number']} ?? ''),
            'alt_id_type' => (string) ($host->{$fields['alt_id_type']} ?? ''),
            'alt_id_number' => (string) ($host->{$fields['alt_id_number']} ?? ''),
            'phone' => (string) ($host->{$fields['phone']} ?? ''),
            'email' => (string) ($host->{$fields['email']} ?? ''),
            'country_code' => (string) ($host->{$fields['country_code']} ?? 'SA'),
            'street_name' => (string) ($host->{$fields['street_name']} ?? ''),
            'city' => (string) ($host->{$fields['city']} ?? ''),
            'addr_building' => (string) ($host->{$fields['addr_building']} ?? ''),
            'addr_postal' => (string) ($host->{$fields['addr_postal']} ?? ''),
            'addr_district' => (string) ($host->{$fields['addr_district']} ?? ''),
            'addr_additional' => (string) ($host->{$fields['addr_additional']} ?? ''),
        ];
    }

    public static function fillHostFromCustomer(object $host, array $fields, Customer $customer): void
    {
        $state = self::stateFromCustomer($customer);
        foreach ($fields as $key => $property) {
            if (! property_exists($host, $property) || ! array_key_exists($key, $state)) {
                continue;
            }
            if ($key === 'vat_registered') {
                $host->{$property} = (bool) $state[$key];

                continue;
            }
            $host->{$property} = is_bool($state[$key]) ? $state[$key] : (string) $state[$key];
        }
    }
}