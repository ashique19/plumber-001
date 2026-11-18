<?php

use App\Support\BusinessProfile;

it('formats national address english print lines without country sa or duplicate district', function () {
    $lines = BusinessProfile::formatNationalAddressEnPrintLines([
        'building' => '7780',
        'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
        'district' => 'Al Murabba',
        'city' => 'Riyadh',
        'postal_code' => '12612',
        'additional' => '3841',
        'country' => 'SA',
    ]);

    expect($lines)->toBe([
        '7780, Prince Abdulaziz bin Musaed bin Jalawi Street',
        'Al Murabba, Riyadh 12612, 3841',
    ]);
});

it('omits district when district equals city', function () {
    $lines = BusinessProfile::formatNationalAddressEnPrintLines([
        'building' => '12',
        'street' => 'Main St',
        'district' => 'Riyadh',
        'city' => 'Riyadh',
        'postal_code' => '11564',
        'country' => 'SA',
    ]);

    expect($lines)->toBe([
        '12, Main St',
        'Riyadh 11564',
    ]);
});