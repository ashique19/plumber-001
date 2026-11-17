<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('shows a disabled download control on the standard uncleared print toolbar without a download link', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-TOOLBAR-BLOCK',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'signing_submission_status' => 'signed_locally',
            'zheart_reporting_status' => 'pending',
            'zheart_remote_submitted' => true,
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-testid="print-toolbar-download-pdf-disabled"')
        ->not->toContain('data-testid="print-toolbar-download-pdf"')
        ->not->toMatch('/href="[^"]*\/pdf"[^>]*download/i');
});

it('returns application/pdf for a cleared standard invoice download', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-CLEARED-PDF',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'signing_submission_status' => 'signed_locally',
            'zheart_reporting_status' => 'cleared',
            'zheart_remote_submitted' => true,
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns 403 when a blocked invoice pdf is requested with Accept application/pdf', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-PDF-403',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'signing_submission_status' => 'signed_locally',
            'zheart_reporting_status' => 'pending',
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->withHeaders(['Accept' => 'application/pdf'])
        ->get(route('admin.invoices.pdf', $invoice))
        ->assertForbidden();
});