<?php

use App\Support\Invoicing\ZHeartBuyerAddressMapper;

it('maps plumber national address to z-heart buyer.address keys', function () {
    $mapped = ZHeartBuyerAddressMapper::toZHeart([
        'building' => '7229',
        'street' => 'King Fahd Rd',
        'district' => 'Al Olaya',
        'city' => 'Riyadh',
        'postal_code' => '12211',
        'country' => 'SA',
    ], 'SA', true);

    expect($mapped)->toMatchArray([
        'country' => 'SA',
        'buildingNumber' => '7229',
        'street' => 'King Fahd Rd',
        'subdivision' => 'Al Olaya',
        'city' => 'Riyadh',
        'postalZone' => '12211',
        'postal' => '12211',
    ]);
});

it('forces sa country for standard clearance payloads when requested', function () {
    $mapped = ZHeartBuyerAddressMapper::toZHeart([
        'city' => 'Riyadh',
    ], 'SA', true);

    expect($mapped['country'] ?? null)->toBe('SA');
});