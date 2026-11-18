<?php

use App\Support\BusinessProfile;

it('formats Arabic national address in RTL order without short national code', function () {
    $na = [
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
        'short_national' => 'RRRD7098',
    ];

    expect(BusinessProfile::formatNationalAddressAr($na))
        ->toBe('7098 أحمد بن الحسين بن شقير، حي الملز، الرياض، 12629')
        ->and(BusinessProfile::formatNationalAddressArHtml($na))
        ->toContain('invoice-national-address-ar')
        ->toContain('dir="ltr"')
        ->toContain('7098')
        ->not->toContain('RRRD7098');
});

it('builds two explicit mPDF Arabic address lines with LTR digit runs', function () {
    $na = [
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
    ];

    $lines = BusinessProfile::formatNationalAddressArPdfLineHtml($na);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('7098')->toContain('أحمد بن الحسين بن شقير')->toContain('،')
        ->and($lines[0])->toContain('invoice-pdf-ltr-digits')
        ->and($lines[1])->toContain('حي الملز')->toContain('الرياض')->toContain('12629');
});
