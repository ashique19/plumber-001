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

function assertA4PrintShowsHeaderAndQr(string $html): void
{
    expect($html)
        ->toContain('invoice-a4__header')
        ->toContain('print-tpl-modern__seller')
        ->toContain('invoice-a4__seller-name-en')
        ->toContain('invoice-a4__seller-name-ar')
        ->toContain('invoice-a4__qr')
        ->toContain('invoice-a4__qr-img');
}

it('includes the seller header and QR on A4 print HTML for issued simplified invoices', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-A4-HEADER-QR',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('simplified-qr-payload'),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertA4PrintShowsHeaderAndQr($html);
    expect($html)->toContain('data-auto-print="0"');
});

it('includes the seller header and QR on A4 print HTML for issued cleared standard invoices', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-A4-HEADER-QR',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('standard-qr-payload'),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe install', 'name_ar' => 'تركيب مواسير', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'buyer_name' => 'Buyer Co',
            'signing_submission_status' => 'submitted',
            'zheart_reporting_status' => 'cleared',
            'zheart_remote_submitted' => true,
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertA4PrintShowsHeaderAndQr($html);
});

it('opens the print dialog when autoprint is requested on the A4 view', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-A4-AUTOPRINT',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('auto-print-qr'),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            'template' => 'modern',
            'autoprint' => 1,
        ]))
        ->assertOk()
        ->assertSee('data-auto-print="1"', false);
});
