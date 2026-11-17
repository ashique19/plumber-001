<?php

namespace App\Support\Customers;

use App\Support\Validation\FieldValidationRules;
use Illuminate\Validation\Rule;

final class CustomerIdentityRules
{
    /**
     * @param  object  $host  Livewire host with public identity properties
     * @param  array<string, string>  $fields
     * @param  array{sa_standard_buyer?: bool, phone_unique?: bool, phone_unique_ignore_id?: int|null, relaxed_address?: bool}  $options
     * @return array<string, mixed>
     */
    public static function forHost(object $host, array $fields, array $options = []): array
    {
        $saStandard = (bool) ($options['sa_standard_buyer'] ?? false);
        $relaxedAddress = (bool) ($options['relaxed_address'] ?? false);
        $ignoreId = $options['phone_unique_ignore_id'] ?? null;

        $phoneRules = FieldValidationRules::saudiMobileOptional();
        if ((bool) ($options['phone_unique'] ?? false)) {
            if ($ignoreId !== null) {
                $phoneRules[] = Rule::unique('customers', 'phone')->ignore($ignoreId);
            } else {
                $phoneRules[] = Rule::unique('customers', 'phone');
            }
        }

        $country = $host->{$fields['country_code']} ?? 'SA';
        $saBuyer = $saStandard && self::isSaCountry($country);

        $rules = [
            $fields['legal_name_ar'] => [
                'nullable',
                'string',
                'max:160',
                function (string $attribute, mixed $value, \Closure $fail) use ($host, $fields): void {
                    $ar = trim((string) $value);
                    $en = trim((string) ($host->{$fields['legal_name_en']} ?? ''));
                    if ($ar === '' && $en === '') {
                        $fail(__('customers.validation.legal_name_required'));
                    }
                },
            ],
            $fields['legal_name_en'] => ['nullable', 'string', 'max:160'],
            $fields['vat_registered'] => ['required', 'boolean'],
            $fields['vat_number'] => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => (bool) $host->{$fields['vat_registered']}),
                new \App\Rules\SaudiVatNumber,
            ],
            $fields['alt_id_type'] => [
                'nullable',
                'string',
                Rule::requiredIf(fn () => ! (bool) $host->{$fields['vat_registered']}),
                Rule::in(ZatcaPartyIdScheme::SCHEMES),
            ],
            $fields['alt_id_number'] => [
                'nullable',
                'string',
                'max:64',
                Rule::requiredIf(fn () => ! (bool) $host->{$fields['vat_registered']}),
            ],
            $fields['email'] => FieldValidationRules::emailOptional(),
            $fields['phone'] => $phoneRules,
            $fields['country_code'] => ['required', 'string', Rule::in(['SA'])],
            $fields['street_name'] => $relaxedAddress
                ? ['nullable', 'string', 'max:120']
                : ['required', 'string', 'max:120'],
            $fields['city'] => $relaxedAddress
                ? ['nullable', 'string', 'max:80']
                : ['required', 'string', 'max:80'],
            $fields['addr_district'] => ['nullable', 'string', 'max:120'],
            $fields['addr_additional'] => ['nullable', 'string', 'max:160'],
            $fields['addr_building'] => $saBuyer
                ? ['required', 'string', 'max:120']
                : ['nullable', 'string', 'max:120'],
            $fields['addr_postal'] => $saBuyer
                ? FieldValidationRules::postalCodeRequired()
                : ['nullable', 'string', 'max:20'],
        ];

        return $rules;
    }

    public static function isSaCountry(?string $code): bool
    {
        return strtoupper(trim((string) $code)) === 'SA';
    }

    /**
     * @return array<string, string>
     */
    public static function hubFields(): array
    {
        return [
            'legal_name_ar' => 'customerLegalNameAr',
            'legal_name_en' => 'customerLegalNameEn',
            'vat_registered' => 'customerVatRegistered',
            'vat_number' => 'customerVat',
            'alt_id_type' => 'customerAltIdType',
            'alt_id_number' => 'customerAltIdNumber',
            'email' => 'customerEmail',
            'phone' => 'customerPhone',
            'country_code' => 'customerCountry',
            'street_name' => 'customerAddrStreet',
            'city' => 'customerAddrCity',
            'addr_building' => 'customerAddrBuilding',
            'addr_postal' => 'customerAddrPostal',
            'addr_district' => 'customerAddrDistrict',
            'addr_additional' => 'customerAddrAdditional',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function quickFields(): array
    {
        return [
            'legal_name_ar' => 'customerQuickNameAr',
            'legal_name_en' => 'customerQuickName',
            'vat_registered' => 'customerQuickVatRegistered',
            'vat_number' => 'customerQuickVat',
            'alt_id_type' => 'customerQuickAltIdType',
            'alt_id_number' => 'customerQuickAltIdNumber',
            'email' => 'customerQuickEmail',
            'phone' => 'customerQuickPhone',
            'country_code' => 'customerQuickCountry',
            'street_name' => 'customerQuickAddrStreet',
            'city' => 'customerQuickAddrCity',
            'addr_building' => 'customerQuickAddrBuilding',
            'addr_postal' => 'customerQuickAddrPostal',
            'addr_district' => 'customerQuickAddrDistrict',
            'addr_additional' => 'customerQuickAddrAdditional',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function buyerFields(): array
    {
        return [
            'legal_name_ar' => 'buyerNameAr',
            'legal_name_en' => 'buyerName',
            'vat_registered' => 'buyerVatRegistered',
            'vat_number' => 'buyerVat',
            'alt_id_type' => 'buyerAltIdType',
            'alt_id_number' => 'buyerAltIdNumber',
            'email' => 'buyerEmail',
            'phone' => 'buyerPhone',
            'country_code' => 'buyerCountry',
            'street_name' => 'addrStreet',
            'city' => 'addrCity',
            'addr_building' => 'addrBuilding',
            'addr_postal' => 'addrPostal',
            'addr_district' => 'addrDistrict',
            'addr_additional' => 'addrAdditional',
        ];
    }
}