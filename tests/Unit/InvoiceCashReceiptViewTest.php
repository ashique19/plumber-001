<?php

use App\Support\Invoicing\InvoiceCashReceiptView;

it('builds seller lines from the same national address for english and arabic', function () {
    $t = [
        'seller_en' => 'RAZAN HADI ALHAYANI Establishment',
        'seller_ar' => 'مؤسسة رزان هادي الحياني',
        'address_en' => 'Al Olaya Street, Building 12, Riyadh 12211, Kingdom of Saudi Arabia',
        'address_ar' => 'حي العليا',
        'national_address' => [
            'building' => '7098',
            'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
            'district' => 'Al Malaz',
            'city' => 'Riyadh',
            'postal_code' => '12629',
            'additional' => '3652',
            'country' => 'SA',
            'street_ar' => 'أحمد بن الحسين بن شقير',
            'district_ar' => 'حي الملز',
            'city_ar' => 'الرياض',
        ],
        'payment_display_en' => 'Cash',
        'payment_display_ar' => 'نقداً',
        'invoice_document_type' => 'simplified',
        'is_simplified' => true,
        'document_number' => 'SIMP-1',
        'lines' => [
            [
                'name_en' => 'Hijab Cap',
                'name_ar' => 'قبعة حجاب',
                'quantity' => 1,
                'quantity_display' => '1',
                'unit_price_ex_vat' => 5.0,
                'taxable_ex_vat' => 5.0,
                'vat_rate' => 15,
                'vat_amount' => 0.75,
                'line_amount_inc_vat' => 5.75,
            ],
            [
                'name_en' => 'Cap Ni-12',
                'name_ar' => null,
                'quantity' => 1,
                'quantity_display' => '1',
                'unit_price_ex_vat' => 6.0,
                'taxable_ex_vat' => 6.0,
                'vat_rate' => 15,
                'vat_amount' => 0.90,
                'line_amount_inc_vat' => 6.90,
            ],
        ],
        'totals' => ['total_inc_vat' => 12.65],
    ];

    $cash = InvoiceCashReceiptView::fromPrintData($t);

    expect($cash['seller_en_lines'])->toBe([
        '7098, Ahmad Ibn Al Hussain Ibn Shuqair St',
        'Al Malaz, Riyadh 12629, 3652',
    ])
        ->and($cash['seller_en_lines'][0])->not->toContain('Al Olaya')
        ->and($cash['seller_ar_lines'][0])->toContain('أحمد بن الحسين بن شقير')
        ->and($cash['seller_ar_lines'][0])->toContain('7098')
        ->and($cash['item_count'])->toEqual(2.0)
        ->and($cash['lines'][0]['unit_price'])->toEqual(5.75)
        ->and($cash['vat_rows'])->toHaveCount(1)
        ->and($cash['vat_rows'][0]['vat_percent'])->toBe(15)
        ->and($cash['vat_rows'][0]['before_vat'])->toEqual(11.0)
        ->and($cash['vat_rows'][0]['incl_vat'])->toEqual(12.65)
        ->and($cash['vat_rows'][0]['vat'])->toEqual(1.65)
        ->and($cash['amount_paid'])->toEqual(12.65)
        ->and($cash['change'])->toEqual(0.0)
        ->and($cash['titles']['en'])->toBe('SIMPLIFIED TAX INVOICE')
        ->and($cash['titles']['ar'])->toBe('فاتورة ضريبية مبسطة');
});
