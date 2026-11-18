<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\BusinessProfile;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\InvoicePrintPresenter;
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
    data_set($settings, 'legal_entity.address_ar', null);
    $business->settings = $settings;
    $business->save();

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('presents issue datetime in Asia/Riyadh from issued_at', function () {
    $issuedAt = Carbon::parse('2026-11-18 15:23:47', 'Asia/Riyadh')->utc();
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-RIYADH-TIME',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $t = InvoicePrintPresenter::fromInvoice(BusinessSetting::singleton()->fresh(), $invoice);

    expect($t['issue_datetime_print'])->toBe('2026-11-18 15:23')
        ->and($t['address_ar_html'])->toContain('invoice-national-address-ar');
});

it('shows bilingual issue datetime label and hides supply on simplified modern print', function () {
    $issuedAt = Carbon::parse('2026-11-18 15:23:00', 'Asia/Riyadh')->utc();
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-NO-SUPPLY',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('2026-11-18 15:23')
        ->toContain('Issue date', false)
        ->toContain('تاريخ ووقت الإصدار')
        ->not->toContain('Supply date', false)
        ->not->toContain('class="invoice-cash__customer"')
        ->not->toContain('<table class="invoice-a4__buyer-table"', false);
});

it('keeps supply date on standard invoice and omits empty buyer table on cash', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-SUPPLY-META',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'supply_date' => '2026-11-20',
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);

    $modern = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->getContent();

    expect($modern)
        ->toContain('Supply date', false)
        ->toContain('2026-11-20')
        ->toContain(BusinessProfile::formatNationalAddressArHtml([
            'building' => '7098',
            'street_ar' => 'أحمد بن الحسين بن شقير',
            'district_ar' => 'حي الملز',
            'city_ar' => 'الرياض',
            'postal_code' => '12629',
        ]) ?? '', false);

    $cash = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->getContent();

    expect($cash)
        ->not->toContain('class="invoice-cash__customer"')
        ->toContain('invoice-national-address-ar');
});

it('does not embed screen status card in PDF html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-PDF-STATUS',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->not->toContain('data-testid="invoice-view-status"')
        ->toContain('تاريخ ووقت الإصدار');
});
