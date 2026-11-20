<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPrintTitles;
use App\Support\Invoicing\InvoicePrintPresenter;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
    $this->business = BusinessSetting::singleton();
});

it('uses credit and debit note titles on print', function () {
    $this->withoutVite();

    $standardParent = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-PARENT-CN',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $credit = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'CN-TITLE-001',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'referenced_invoice_id' => $standardParent->id,
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $debit = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'DN-TITLE-001',
        'document_type' => InvoiceDocumentType::DebitNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'referenced_invoice_id' => $standardParent->id,
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

  $creditHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $credit, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($creditHtml)
        ->toContain('CREDIT NOTE', false)
        ->toContain('إشعار دائن', false)
        ->not->toContain('STANDARD TAX INVOICE', false);

    $debitHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $debit, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($debitHtml)
        ->toContain('DEBIT NOTE', false)
        ->toContain('إشعار مدين', false)
        ->not->toContain('STANDARD TAX INVOICE', false);
});

it('uses simplified credit note titles when referencing a simplified invoice', function () {
    $simplifiedParent = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PARENT-CN',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $credit = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SCN-TITLE-001',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'referenced_invoice_id' => $simplifiedParent->id,
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $t = InvoicePrintPresenter::fromInvoice($this->business, $credit);
    $titles = DocumentPrintTitles::forContext($t);

    expect($titles['en'])->toBe('SIMPLIFIED CREDIT NOTE')
        ->and($titles['ar'])->toBe('إشعار دائن مبسط');
});

it('pairs english and arabic seller header typography in modern print css', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toMatch('/\.print-tpl-modern__seller \.invoice-a4__seller-name-en,\s*\n\.print-tpl-modern__seller \.invoice-a4__seller-name-ar/')
        ->toMatch('/font-size:\s*9pt;\s*\n\s*line-height:\s*1\.35/s')
        ->toContain('.invoice-a4__seller-en-stack', false);
});

it('renders seller vat and cr in one english stack without extra id rows', function () {
    $this->withoutVite();

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-EN-STACK',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    $sheet = \App\Support\Invoicing\PrintSheetFactory::invoice($invoice, \Illuminate\Http\Request::create('/', 'GET', ['template' => 'modern']), true);
    $pdfHtml = view($sheet['view'], $sheet['data'])->render();

    expect($pdfHtml)
        ->toContain('invoice-pdf-seller-en-compact', false)
        ->toContain('invoice-pdf-seller-ar-compact', false)
        ->toContain('invoice-pdf-seller-en-body', false)
        ->toContain('invoice-pdf-seller-ar-body', false)
        ->not->toContain('invoice-a4__header-row--ids', false);

    expect($html)
        ->toContain('invoice-a4__seller-en-stack', false)
        ->toContain('invoice-a4__seller-ar-stack', false);
});