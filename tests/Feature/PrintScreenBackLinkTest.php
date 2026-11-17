<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceLifecycleTab;
use App\Support\Hub\HubInvoicePrintLinks;
use App\Support\Invoicing\PrintScreenBackUrl;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('carries return query on hub invoice print urls', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-BACK-LINK',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
    ]);

    $return = 'lifecycle=awaiting_clearance';
    $urls = HubInvoicePrintLinks::urls($invoice, $return);

    expect($urls['print'])->toContain('return='.urlencode($return))
        ->and($urls['fallback_back'])->toBe(route('admin.invoices', ['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE]));
});

it('renders invoice print back link to index with return lifecycle filter', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-BACK-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
    ]);

    $return = http_build_query(['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE]);
    $expected = route('admin.invoices', ['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            PrintScreenBackUrl::RETURN_PARAM => $return,
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-testid="print-screen-back"', false)
        ->and($html)->toContain(e($expected));
});

it('renders quotation print back link using return lifecycle', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'quotation_number' => 'Q-BACK-1',
        'validity_until' => now()->addDays(7),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
    ]);

    $expected = route('admin.quotations', ['lifecycle' => 'open']);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', [
            'quotation' => $quotation,
            PrintScreenBackUrl::RETURN_PARAM => 'lifecycle=open',
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('data-testid="print-screen-back"', false)
        ->and($html)->toContain(e($expected));
});