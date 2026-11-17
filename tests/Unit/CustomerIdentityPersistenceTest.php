<?php

use App\Models\Customer;
use App\Support\Customers\CustomerIdentityPersistence;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists vat registered buyers and clears alternate id', function () {
    $customer = Customer::query()->create([
        'name' => 'Legacy',
        'phone' => '0501112233',
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [],
    ]);

    CustomerIdentityPersistence::applyToCustomer($customer, [
        'legal_name_ar' => 'شركة',
        'legal_name_en' => 'Acme LLC',
        'vat_registered' => true,
        'vat_number' => '300000000000003',
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1010998899',
        'phone' => '0501112233',
        'email' => 'buyer@example.test',
        'country_code' => 'SA',
        'street_name' => 'King Fahd Rd',
        'city' => 'Riyadh',
        'addr_building' => '10',
        'addr_postal' => '11564',
        'addr_district' => '',
        'addr_additional' => '',
    ]);
    $customer->save();

    $customer->refresh();
    expect($customer->vat_registered)->toBeTrue()
        ->and($customer->vat_number)->toBe('300000000000003')
        ->and($customer->alt_id_type)->toBeNull()
        ->and($customer->legal_name_en)->toBe('Acme LLC')
        ->and($customer->street_name)->toBe('King Fahd Rd')
        ->and($customer->city)->toBe('Riyadh');
});

it('stores crn as alternate id when not vat registered', function () {
    $customer = new Customer([
        'referral_code' => '',
        'points_balance' => 0,
    ]);

    CustomerIdentityPersistence::applyToCustomer($customer, [
        'legal_name_en' => 'Buyer LLC',
        'legal_name_ar' => '',
        'vat_registered' => false,
        'vat_number' => '',
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1010998899',
        'phone' => '',
        'email' => '',
        'country_code' => 'SA',
        'street_name' => 'Street 1',
        'city' => 'Riyadh',
        'addr_building' => '1',
        'addr_postal' => '11564',
        'addr_district' => '',
        'addr_additional' => '',
    ]);
    $customer->save();

    expect($customer->alt_id_type)->toBe('CRN')
        ->and($customer->alt_id_number)->toBe('1010998899')
        ->and(data_get($customer->profile, 'cr_number'))->toBe('1010998899');
});

it('hydrates host state from customer including legacy profile', function () {
    $customer = Customer::query()->create([
        'name' => 'Buyer LLC',
        'phone' => '0512345678',
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [
            'cr_number' => '1010123456',
            'national_address' => [
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
        ],
    ]);

    $host = new class
    {
        public string $buyerName = '';

        public bool $buyerVatRegistered = false;

        public string $buyerVat = '';

        public string $buyerAltIdType = '';

        public string $buyerAltIdNumber = '';

        public string $buyerPhone = '';

        public string $buyerEmail = '';

        public string $buyerCountry = 'SA';

        public string $addrStreet = '';

        public string $addrCity = '';

        public string $addrBuilding = '';

        public string $addrPostal = '';

        public string $addrDistrict = '';

        public string $addrAdditional = '';
    };

    CustomerIdentityPersistence::fillHostFromCustomer(
        $host,
        \App\Support\Customers\CustomerIdentityRules::buyerFields(),
        $customer,
    );

    expect($host->buyerName)->toBe('Buyer LLC')
        ->and($host->buyerAltIdType)->toBe('CRN')
        ->and($host->buyerAltIdNumber)->toBe('1010123456')
        ->and($host->addrStreet)->toBe('King Fahd Rd');
});

it('forces saudi country and english city on persistence', function () {
    $customer = Customer::query()->create([
        'name' => 'Buyer',
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => [],
    ]);

    CustomerIdentityPersistence::applyToCustomer($customer, [
        'legal_name_en' => 'Buyer',
        'legal_name_ar' => '',
        'vat_registered' => false,
        'vat_number' => '',
        'alt_id_type' => '',
        'alt_id_number' => '',
        'phone' => '',
        'email' => '',
        'country_code' => 'AE',
        'street_name' => '',
        'city' => 'Jeddah',
        'addr_building' => '',
        'addr_postal' => '',
        'addr_district' => '',
        'addr_additional' => '',
    ]);
    $customer->save();
    $customer->refresh();

    expect($customer->country_code)->toBe('SA')
        ->and(data_get($customer->profile, 'country'))->toBe('SA')
        ->and($customer->city)->toBe('Jeddah');

    CustomerIdentityPersistence::applyToCustomer($customer, [
        'legal_name_en' => 'Buyer',
        'legal_name_ar' => '',
        'vat_registered' => false,
        'vat_number' => '',
        'alt_id_type' => '',
        'alt_id_number' => '',
        'phone' => '',
        'email' => '',
        'country_code' => 'SA',
        'street_name' => '',
        'city' => 'الرياض',
        'addr_building' => '',
        'addr_postal' => '',
        'addr_district' => '',
        'addr_additional' => '',
    ]);
    $customer->save();
    $customer->refresh();

    expect($customer->city)->toBe('Riyadh')
        ->and(data_get($customer->profile, 'national_address.city'))->toBe('Riyadh');
});