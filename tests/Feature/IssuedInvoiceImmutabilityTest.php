<?php

use App\Enums\InvoiceDocumentType;
use App\Exceptions\IssuedInvoiceImmutableException;
use App\Services\InvoiceIssueService;
use App\Business\BusinessContext;
use Tests\Support\InteractsWithBusiness;

uses(InteractsWithBusiness::class);

afterEach(function () {
    BusinessContext::clear();
});

it('blocks updates to issued invoices', function () {
    $tenant = $this->seedBusiness('immut-co');
    $branch = \App\Models\Branch::query()->first();
    $this->actingAsBusiness($tenant);

    /** @var InvoiceIssueService $issuer */
    $issuer = app(InvoiceIssueService::class);
    $line = [['name' => 'Service', 'quantity' => 1, 'unit_price_ex_vat' => 200, 'vat_rate' => 15]];

    $invoice = $issuer->issueDraft($branch, 'INV-IM-1', InvoiceDocumentType::Standard, $line);
    $invoice = $issuer->issue($invoice);

    expect(fn () => $invoice->update(['document_number' => 'HACKED']))
        ->toThrow(IssuedInvoiceImmutableException::class);
});
