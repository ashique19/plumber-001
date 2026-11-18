<?php

use App\Support\Invoicing\InvoicePrintTotalsBlock;

it('hides net due when it matches total without retention', function () {
    $t = ['net_due_inc_vat' => 11.5];
    $tot = ['total_inc_vat' => 11.5, 'retention_amount' => 0];

    expect(InvoicePrintTotalsBlock::shouldShowNetDue($t, $tot))->toBeFalse();
});

it('shows net due when retention or partial payment applies', function () {
    expect(InvoicePrintTotalsBlock::shouldShowNetDue(
        ['net_due_inc_vat' => 10],
        ['total_inc_vat' => 11.5, 'retention_amount' => 0],
    ))->toBeTrue();

    expect(InvoicePrintTotalsBlock::shouldShowNetDue(
        ['net_due_inc_vat' => 11.5],
        ['total_inc_vat' => 11.5, 'retention_amount' => 1],
    ))->toBeTrue();
});
