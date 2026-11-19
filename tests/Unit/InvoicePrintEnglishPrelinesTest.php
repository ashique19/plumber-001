<?php

use App\Support\Invoicing\InvoicePrintEnglishPrelines;

it('balances eleven riyals amount words without a halalas-only second line', function () {
    $wrapped = InvoicePrintEnglishPrelines::amountWordsBlock('Eleven Saudi Riyals and Fifty Halalas only');

    expect($wrapped['lines'])->toHaveCount(2)
        ->and($wrapped['lines'][0])->toBe('Eleven Saudi Riyals and')
        ->and($wrapped['lines'][1])->toBe('Fifty Halalas only');

    foreach ($wrapped['lines'] as $line) {
        expect(count(preg_split('/\s+/u', trim($line)) ?: []))->toBeGreaterThan(1);
        expect(strtolower(trim($line)))->not->toBe('only');
    }
});

it('keeps short english amount words on one nowrap line', function () {
    $wrapped = InvoicePrintEnglishPrelines::amountWordsBlock('Ten Saudi Riyals only');

    expect($wrapped['single_line'])->toBeTrue()
        ->and($wrapped['lines'])->toHaveCount(1);
});