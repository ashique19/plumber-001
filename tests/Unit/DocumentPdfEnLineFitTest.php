<?php

use App\Support\Invoicing\DocumentPdfEnLineFit;

it('keeps base size for short seller name at 74mm', function () {
    $name = 'RAZAN HADI ALHAYANI Establishment';
    expect(DocumentPdfEnLineFit::fitPointSize($name, 74, 8))
        ->toBeGreaterThanOrEqual(7.5);
});

it('shrinks font for very long english line', function () {
    $long = str_repeat('A', 120);
    $fitted = DocumentPdfEnLineFit::fitPointSize($long, 74, 8);
    expect($fitted)->toBeLessThan(8);
});

it('includes nowrap in inline style', function () {
    $style = DocumentPdfEnLineFit::inlineStyle('Test Line', 74, 7.5);
    expect($style)->toContain('white-space:nowrap');
});