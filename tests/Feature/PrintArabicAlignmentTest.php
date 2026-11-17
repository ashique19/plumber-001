<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('marks Arabic print labels with invoice-print-ar, dir rtl, and right-align CSS', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));
    expect($css)
        ->toContain('.invoice-print-root .invoice-print-ar')
        ->toContain('text-align: right')
        ->not->toContain(".invoice-a4__buyer-table-label.ar {\n    width: 22%;\n    font-family: Tajawal, sans-serif;\n    text-align: end;");

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-AR-ALIGN',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'buyer_name' => 'QA Customer',
            'buyer_name_ar' => 'عميل اختبار',
            'lines' => [
                ['name' => 'QA Product A', 'name_ar' => 'منتج اختبار أ', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
        ],
    ]);

    $modernHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertModernArabicPrintCells($modernHtml);

    $classicHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'classic']))
        ->assertOk()
        ->getContent();

    expect($classicHtml)
        ->toMatch('/print-classic__cell-label ar invoice-print-ar[^>]*dir="rtl"/')
        ->toMatch('/invoice-print-ar[^>]*dir="rtl"[^>]*>[^<]*تفاصيل/');

    $cashHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->assertOk()
        ->getContent();

    expect($cashHtml)->toMatch('/invoice-print-ar[^>]*dir="rtl"/');

    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'QUO-AR-ALIGN',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'name_ar' => 'صمام', 'quantity' => 1, 'unit_price_ex_vat' => 40, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $quoHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertModernArabicPrintCells($quoHtml);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $pdfWrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($pdfWrapped)
        ->toContain('invoice-print-ar')
        ->toContain('text-align:right!important');
});

function assertModernArabicPrintCells(string $html): void
{
    expect($html)
        ->toMatch('/invoice-a4__buyer-table-label ar invoice-print-ar[^>]*dir="rtl"/')
        ->toMatch('/print-tpl-modern__totals-ar invoice-print-ar[^>]*dir="rtl"/')
        ->toMatch('/invoice-a4__desc-ar invoice-print-ar[^>]*dir="rtl"/')
        ->toMatch('/invoice-a4__seller-side--ar invoice-print-ar[^>]*dir="rtl"/');
}