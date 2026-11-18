<?php

use App\Support\Invoicing\SellerHeaderIds;

it('builds bilingual seller header id lines with ltr digit spans', function () {
    $t = [
        'trn' => '311754836700003',
        'cr_number' => '7034049994',
    ];

    $en = SellerHeaderIds::linesForSide($t, 'en');
    $ar = SellerHeaderIds::linesForSide($t, 'ar');

    expect($en)->toHaveCount(2)
        ->and($en[0]['label'])->toBe('VAT No.')
        ->and($en[1]['label'])->toBe('CR No.')
        ->and($en[0]['value_html'])->toContain('invoice-print-ltr-num')
        ->and($ar[0]['label'])->toBe('الرقم الضريبي')
        ->and($ar[1]['label'])->toBe('السجل التجاري');
});