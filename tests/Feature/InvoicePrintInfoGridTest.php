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
        ->not->toContain('<colgroup', false)
        ->toContain('VAT No.', false)
        ->toContain('Issue date', false)
        ->toContain('invoice-print-info-grid__label--nowrap', false)
        ->not->toContain('invoice-print-seller-ids', false)
        ->not->toContain('invoice-print-labeled-row', false);

    $vatPos = strpos($html, 'VAT No.');
    $invoicePos = strpos($html, 'Invoice No.');
    expect($vatPos)->toBeLessThan($invoicePos);

    expect($html)->toMatch(
        '/VAT No\.<\/td>\s*<td[^>]*invoice-print-info-grid__value[^>]*>.*?<\/td>\s*<td[^>]*invoice-print-info-grid__ar[^>]*>الرقم الضريبي/s',
    )->toContain('invoice-national-address-ar__line', false);
});

it('includes mPDF-friendly fixed column rules in pdf wrapper css', function () {
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
        ->toContain('invoice-print-info-grid__value{text-align:left!important', false);
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
