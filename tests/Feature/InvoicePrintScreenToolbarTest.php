<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    app()->setLocale('en');
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

function makeIssuedInvoice(InvoiceDocumentType $type): Invoice
{
    return Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'TB-'.$type->value.'-'.uniqid(),
        'document_type' => $type,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('qr-toolbar'),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);
}

it('renders a screen-only toolbar on the A4 and cash print views', function (string $layout) {
    $invoice = makeIssuedInvoice($layout === 'a4' ? InvoiceDocumentType::Standard : InvoiceDocumentType::Simplified);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => $layout, 'autoprint' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-testid="print-screen-toolbar"')
        ->toContain('data-testid="print-screen-back"')
        ->toContain('data-testid="print-screen-print-again"')
        ->toContain('data-testid="print-screen-close"')
        ->toContain(__('print.back_to_invoice'))
        ->toContain(__('print.print_again'))
        ->toContain(__('print.close_window'))
        ->toContain('data-print-close')
        ->toContain('data-close-fallback');

    expect(strpos($html, 'data-testid="print-screen-toolbar"'))
        ->toBeLessThan(strpos($html, 'invoice-print-root'));
})->with(['a4', 'cash']);

it('hides the print toolbar in print and PDF css', function () {
    $css = file_get_contents(resource_path('css/invoice-print.css')) ?: '';
    $pdfCss = DocumentPdfRenderer::wrapHtml('<div class="invoice-print-toolbar">x</div>');

    expect($css)
        ->toMatch('/@media print[\s\S]*\.no-print\s*\{\s*display:\s*none/')
        ->and($pdfCss)->toContain('.invoice-print-toolbar,.no-print,.invoice-zatca-notice{display:none!important}');
});

it('does not render the screen toolbar in embedded print preview', function () {
    $invoice = makeIssuedInvoice(InvoiceDocumentType::Standard);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'embed' => 1]))
        ->assertOk()
        ->assertDontSee('data-testid="print-screen-toolbar"', false);
});
