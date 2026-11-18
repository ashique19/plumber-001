<?php

use App\Support\Invoicing\DocumentPdfArabicLineWrap;

it('splits long description arabic into explicit rtl line blocks', function () {
    $long = 'تسليك وتصوير تلفزيوني لتدفق الصرف الصحي مع عدة مواد كيميائية وضمان جودة العمل المطلوبة';
    $html = '<div class="invoice-print-root--pdf"><div class="invoice-a4__desc-ar invoice-print-ar" dir="rtl">'.$long.'</div></div>';

    $wrapped = DocumentPdfArabicLineWrap::apply($html);

    expect(substr_count($wrapped, 'invoice-pdf-ar-preline'))->toBeGreaterThan(1)
        ->and($wrapped)->toContain('dir="rtl"');
});