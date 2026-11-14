<?php

use App\Support\Invoicing\InvoiceLineMath;

it('computes progress billing qty with three decimals and two dp line net', function () {
    $net = InvoiceLineMath::lineNetExVat(29_150, 0.05);

    expect($net)->toBe(1457.50)
        ->and(InvoiceLineMath::formatQuantity(0.05))->toBe('0.05');
});

it('rounds line vat on net base', function () {
    $net = InvoiceLineMath::lineNetExVat(100, 1);
    $vat = InvoiceLineMath::lineVatAmount($net, 15);

    expect($vat)->toBe(15.0);
});