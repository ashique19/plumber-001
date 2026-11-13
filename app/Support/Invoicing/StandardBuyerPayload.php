<?php

namespace App\Support\Invoicing;

use App\Models\Customer;
use App\Rules\SaudiVatNumber;
use Illuminate\Support\Facades\Validator;

final class StandardBuyerPayload
{
    /**
     * @return array{buyer_name: string, buyer_vat: string, buyer_cr: string, buyer_national_address: array<string, string|null>}
     */
    public static function fromCustomer(?Customer $customer): array
    {
        if ($customer === null) {
            throw new \InvalidArgumentException(__('pos.errors.standard_buyer_required'));
        }

        return self::fromArrays(
            trim($customer->name),
            (array) ($customer->profile ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{buyer_name: string, buyer_vat: string, buyer_cr: string, buyer_national_address: array<string, string|null>}
     */
    public static function fromArrays(string $buyerName, array $profile): array
    {
        $vat = preg_replace('/\D/', '', (string) data_get($profile, 'vat_number', '')) ?? '';
        $cr = trim((string) data_get($profile, 'cr_number', ''));
        $address = (array) data_get($profile, 'national_address', []);

        if ($buyerName === '') {
            throw new \InvalidArgumentException(__('pos.errors.standard_buyer_required'));
        }

        if ($vat === '' && $cr === '') {
            throw new \InvalidArgumentException(__('invoices.errors.buyer_id_required'));
        }

        if ($vat !== '') {
            Validator::make(['vat' => $vat], ['vat' => [new SaudiVatNumber]])->validate();
        }

        if ($cr !== '' && ! preg_match('/^\d{1,10}$/', $cr)) {
            throw new \InvalidArgumentException(__('invoices.errors.buyer_cr_invalid'));
        }

        $building = trim((string) ($address['building'] ?? ''));
        $street = trim((string) ($address['street'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));
        $postal = preg_replace('/\D/', '', (string) ($address['postal_code'] ?? '')) ?? '';

        if ($building === '' || $street === '' || $city === '' || ! preg_match('/^\d{5}$/', $postal)) {
            throw new \InvalidArgumentException(__('pos.errors.standard_buyer_address_required'));
        }

        return [
            'buyer_name' => $buyerName,
            'buyer_vat' => $vat,
            'buyer_cr' => $cr,
            'buyer_national_address' => [
                'building' => $building,
                'street' => $street,
                'district' => trim((string) ($address['district'] ?? '')),
                'city' => $city,
                'postal_code' => $postal,
                'additional' => trim((string) ($address['additional'] ?? '')),
            ],
        ];
    }
}
