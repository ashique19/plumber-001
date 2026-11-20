<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Carbon\Carbon;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'legal_entity.national_address', [
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
    ]);
    $business->settings = $settings;
    $business->save();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('uses one fixed info grid table for seller ids and document meta on modern print', function () {
    $issuedAt = Carbon::parse('2026-11-18 15:23:00', 'Asia/Riyadh')->utc();
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-INFO-GRID',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'due_date' => '2026-12-01',
        'payload' => [
            'supply_date' => '2026-11-20',
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('<table class="invoice-print-info-grid"', false)
        ->toContain('invoice-print-info-grid__col-en', false)
        ->toContain('invoice-print-seller-header-ids', false)
        ->toContain('invoice-a4__seller-ar-stack', false)
        ->toContain('CR No.', false)
        ->toContain('Invoice No.', false)
        ->toContain('Issue date', false)
        ->toContain('invoice-print-info-grid__label--nowrap', false)
        ->not->toContain('invoice-print-seller-ids', false)
        ->not->toContain('invoice-print-labeled-row', false);

    $headerVatPos = strpos($html, 'invoice-print-seller-header-ids');
    $gridInvoicePos = strpos($html, 'invoice-print-info-grid');
    expect($headerVatPos)->toBeLessThan($gridInvoicePos);

    expect($html)->not->toMatch(
        '/VAT No\.<\/td>\s*<td[^>]*invoice-print-info-grid__value[^>]*>/s',
    )->toContain('invoice-national-address-ar__line', false);

    $printCss = (string) file_get_contents(resource_path('css/invoice-print.css'));
    expect($printCss)
        ->toContain('.invoice-a4__seller-en-stack .invoice-print-seller-header-ids__line')
        ->toMatch('/\.invoice-a4__seller-en-stack \.invoice-print-seller-header-ids__line,\s*\n\.invoice-a4__seller-ar-stack \.invoice-print-seller-header-ids__line--ar\s*\{[^}]*font-size:\s*9pt/s');
});

it('includes fixed column rules in Dompdf wrapper css', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-MPDF-GRID',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->toContain('invoice-print-info-grid', false)
        ->toContain('table-layout:fixed!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__value{text-align:left!important', false)
        ->toContain('.invoice-pdf-seller-line{display:block!important;margin:0!important;padding:0!important;overflow:visible!important;white-space:normal!important', false)
        ->toContain('.invoice-pdf-seller-ar-compact{font-size:8.5pt!important', false)
        ->toContain('.invoice-a4__header-cell--logo{width:20%!important', false)
        ->not->toContain('invoice-pdf-seller-line{overflow:hidden!important', false)
        ->toContain('invoice-print-root--pdf .invoice-pdf-sans-value{font-family:dejavusans!important;font-style:normal!important;font-weight:normal!important;font-size:9.5pt!important', false)
        ->toContain('invoice-print-totals__row--strong .invoice-print-totals__value{font-size:12pt!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-a4__title{margin:1px 0;font-size:14pt!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-en-name{margin:0!important;padding:0!important;font-weight:700!important;white-space:normal!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-en{width:21%!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-value{width:44%!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-ar{width:35%!important', false)
        ->toContain('padding:1px 10px 1px 2px!important', false)
        ->toContain('font-family:inherit!important;font-size:inherit!important', false)
        ->toContain('invoice-pdf-seller-en-compact', false)
        ->toContain('invoice-pdf-seller-ar-compact', false)
        ->toContain('invoice-pdf-seller-ar-name', false)
        ->toContain('invoice-pdf-seller-ar-body', false)
        ->toContain('line-height:9.75pt!important', false)
        ->toContain('invoice-a4__header .invoice-national-address-ar--pdf{max-width:none!important', false)
        ->toContain('unicode-bidi:isolate!important', false)
        ->toContain('invoice-national-address-ar--pdf{margin:0!important;line-height:1.3!important', false)
        ->not->toContain('line-height:1.45!important', false)
        ->not->toContain('invoice-print-seller-header-ids__value{font-size:9pt!important', false);
});

it('renders cash payment values in LTR bidi order', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PAY-BIDI',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('invoice-print-bidi-value', false)
        ->toContain('Cash / نقداً', false);
});
