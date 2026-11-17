<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoicePrintLinks;
use App\Support\Hub\HubQuotationPrintLinks;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('downloads an invoice PDF as an attachment named after the document number', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'SIMP-PDF-001',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'name_ar' => 'زيارة', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
    ]);

    $urls = HubInvoicePrintLinks::urls($invoice);
    expect($urls['download'])->toBe(route('admin.invoices.pdf', $invoice))
        ->and($urls['print'])->toBe(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']));

    $this->actingAs($this->owner, 'staff')
        ->get($urls['download'])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="SIMP-PDF-001.pdf"');
});

it('downloads a quotation PDF without opening the print preview', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'QUO-PDF-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(10)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    expect(HubQuotationPrintLinks::urls($quotation)['download'])
        ->toBe(route('admin.quotations.pdf', $quotation));

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.pdf', $quotation))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="QUO-PDF-001.pdf"');
});

it('wraps PDF HTML with an Arabic-capable default font and SAR fallback, without question-mark glyphs', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-PDF-AR-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                [
                    'name' => 'Service visit',
                    'name_ar' => 'زيارة صيانة',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 50,
                    'vat_rate' => 15,
                ],
            ],
            'buyer_name' => 'هيئة الزكاة والضريبة والجمارك',
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('invoice-sar-symbol__svg')
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('width:0.95em!important')
        ->toContain('dejavusans, sans-serif')
        ->not->toContain('???');

    $binary = DocumentPdfRenderer::render($html);
    expect($binary)
        ->toStartWith('%PDF')
        ->and($binary)->not->toContain('???');
});

it('uses the SAR symbol and a page footer on classic template B prints', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-CLASSIC-SAR',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'classic']))
        ->assertOk()
        ->assertSee('invoice-sar-symbol__svg', false)
        ->assertSee('print-classic__sheet-footer', false)
        ->assertSee('invoice-print-page-label', false)
        ->assertDontSee('Page 0 of 0', false);
});
