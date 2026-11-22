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
        ->and($lines[0])->toContain('7098')->toContain('أحمد بن الحسين بن شقير')
        ->and($lines[0])->not->toContain('شقير،')
        ->and($lines[0])->toContain('invoice-pdf-ltr-digits')
        ->and($lines[1])->toContain('حي الملز')->toContain('الرياض')->toContain('12629')
        ->and($lines[1])->not->toContain('invoice-pdf-ltr-digits')
        ->and(BusinessProfile::wrapPdfAddressDigitRunsInEscapedLine($lines[1]))->toContain('invoice-pdf-ltr-digits');
});

it('builds typography-safe dompdf seller header arabic lines with the building number after the street', function () {
    $na = [
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
        'additional' => '3652',
    ];

    $lines = BusinessProfile::formatNationalAddressArDompdfPlainLines($na);

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('invoice-pdf-ar-street-line')
        ->and($lines[0])->toContain('>7098</span>')
        ->and($lines[0])->toContain('أحمد بن الحسين بن شقير')
        ->and($lines[0])->not->toContain('شقير،')
        ->and(strpos($lines[0], 'شقير') < strpos($lines[0], '>7098</span>'))->toBeTrue()
        ->and($lines[1])->toContain('12629')->toContain('3652')
        ->and($lines[1])->not->toContain('invoice-pdf-ltr-digits')
        ->and(html_entity_decode($lines[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        ->toBe("حي الملز، الرياض 12629، 3652");
});

it('keeps english street suffix on one dompdf header line', function () {
    $na = [
        'building' => '7098',
        'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
        'district' => 'Al Malaz',
        'city' => 'Riyadh',
        'postal_code' => '12629',
    ];

    $lines = BusinessProfile::formatNationalAddressEnDompdfPrintLines($na);

    expect($lines[0])->toBe('7098, Ahmad Ibn Al Hussain Ibn Shuqair'."\u{00A0}".'St');
});
