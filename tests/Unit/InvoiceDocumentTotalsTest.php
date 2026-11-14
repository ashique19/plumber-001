<?php

use App\Data\InvoiceLineData;
use App\Support\Invoicing\InvoiceDocumentTotals;

it('applies invoice discount before vat and retention after vat without changing zatca base', function () {
    $lines = [new InvoiceLineData('Contract works', 0.05, 29_150, 15)];
    $totals = InvoiceDocumentTotals::compute($lines, invoiceDiscountExVat: 50, retentionPercent: 5);

    expect($totals['subtotal_ex_vat'])->toBe(1457.50)
        ->and($totals['invoice_discount_ex_vat'])->toBe(50.0)
        ->and($totals['total_vat'])->toBe(211.13)
        ->and($totals['total_inc_vat'])->toBe(1618.63)
        ->and($totals['retention_amount'])->toBe(80.93)
        ->and($totals['net_due_inc_vat'])->toBe(1537.70);
});