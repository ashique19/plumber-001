<?php

use App\Support\Invoicing\SarAmountInWords;

it('formats english amount in words with halalas', function () {
    $words = SarAmountInWords::format(2251.13);

    expect($words['en'])->toBe('two thousand two hundred fifty-one Saudi Riyals and thirteen Halalas only')
        ->and($words['ar'])->toContain('فقط');
});