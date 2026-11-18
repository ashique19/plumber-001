<?php

it('defines print labeled-row, meta grid, and product line column alignment in invoice print css', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toContain('.invoice-print-labeled-row')
        ->toContain('.invoice-print-info-grid')
        ->toContain('table-layout: fixed')
        ->toContain('.invoice-print-th-label')
        ->toContain('.invoice-a4__table--product-lines th.col-num')
        ->toContain('.invoice-a4__table--product-lines th.col-index');
});

it('renders print-labeled-row component markup', function () {
    $html = view('components.invoices.print-labeled-row', [
        'en' => 'Invoice No.',
        'ar' => 'رقم الفاتورة',
        'value' => 'SIMP-001',
        'nowrap' => true,
    ])->render();

    expect($html)
        ->toContain('invoice-print-labeled-row')
        ->toContain('invoice-print-labeled-row__en')
        ->toContain('invoice-print-labeled-row__value')
        ->toContain('invoice-print-labeled-row__ar')
        ->toContain('SIMP-001');
});
