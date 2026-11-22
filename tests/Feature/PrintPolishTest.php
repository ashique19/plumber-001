<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Quotations\QuotationHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('enlarges the centered print logo and right-aligns Arabic print text', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'))
        .(string) file_get_contents(resource_path('css/print-template-classic.css'));

    expect($css)
        ->toContain('max-height: 96px')
        ->toContain("text-align: right")
        ->toContain('invoice-sar-symbol');
});

it('keeps modern totals as separate English, Arabic, and amount columns', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toContain('.invoice-print-totals__en')
        ->toContain('.invoice-print-totals__ar')
        ->toContain('.invoice-print-totals__value')
        ->toContain('table-layout: fixed');

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-TOTALS-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('print-tpl-modern__totals')
        ->toContain('invoice-print-totals__en')
        ->toContain('invoice-print-totals__ar')
        ->toContain('invoice-print-totals__value');
});

it('renders plain SAR text on invoice print', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-SAR-001',
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
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'locale' => 'en']))
        ->assertOk()
        ->assertSee('invoice-sar-symbol', false)
        ->assertSee('>SAR</span>', false)
        ->assertDontSee('invoice-sar-symbol__svg', false)
        ->assertDontSee('sar-riyal-symbol.svg', false);
});

it('titles quotations without tax-invoice wording, type badges, or QR', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'QUO-POLISH-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(14)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $leaks = quotationInvoiceTypeLeaks();

    foreach (['classic', 'modern'] as $template) {
        $html = $this->actingAs($this->owner, 'staff')
            ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => $template, 'locale' => 'en']))
            ->assertOk()
            ->assertSee(trans('quotations.print_title', [], 'en'), false)
            ->assertSee(trans('quotations.print_title', [], 'ar'), false)
            ->assertDontSee(trans('quotations.print_title', [], 'bn'), false)
            ->getContent();

        assertNoQuotationInvoiceTypeLeaks($html, $leaks);
    }

    $pdfHtml = view(
        'print.invoices.modern',
        \App\Support\Invoicing\PrintSheetFactory::quotation(
            $quotation,
            \Illuminate\Http\Request::create('/', 'GET', ['template' => 'modern']),
            true,
        )['data'],
    )->render();

    expect($pdfHtml)
        ->toContain(trans('quotations.print_title', [], 'en'))
        ->toContain(trans('quotations.print_title', [], 'ar'))
        ->not->toContain(trans('quotations.print_title', [], 'bn'));
    assertNoQuotationInvoiceTypeLeaks($pdfHtml, $leaks);

    $hub = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->html();
    assertNoQuotationInvoiceTypeLeaks($hub, $leaks);

    $create = Livewire::actingAs($this->owner, 'staff')
        ->test(\App\Livewire\Quotations\ManageQuotation::class)
        ->html();
    assertNoQuotationInvoiceTypeLeaks($create, $leaks);

    $edit = Livewire::actingAs($this->owner, 'staff')
        ->test(\App\Livewire\Quotations\ManageQuotation::class, ['quotation' => $quotation])
        ->html();
    assertNoQuotationInvoiceTypeLeaks($edit, $leaks);
});

function quotationInvoiceTypeLeaks(): array
{
    return [
        'Simplified tax invoice',
        'Standard tax invoice',
        'SIMPLIFIED TAX INVOICE',
        'STANDARD TAX INVOICE',
        'simplified/standard',
        trans('invoices.type.simplified_tax', [], 'en'),
        trans('invoices.type.standard_tax', [], 'en'),
        trans('quotations.target_invoice_mode', [], 'en'),
        trans('quotations.invoice_mode_simplified', [], 'en'),
        trans('quotations.invoice_mode_standard', [], 'en'),
        trans('invoices.invoice_type', [], 'en'),
        'receipt-qr',
        'qr_tlv',
        'ZATCA',
    ];
}

function assertNoQuotationInvoiceTypeLeaks(string $html, array $leaks): void
{
    // Custom-line VAT category labels use "Standard 15%" / similar catalog copy.
    $html = str_replace(['Standard 15%', 'Zero-rated'], '', $html);

    foreach ($leaks as $leak) {
        expect($html)->not->toContain($leak);
    }
}
