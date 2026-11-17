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
use App\Support\LocaleCookie;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('renders one-page PDFs for single-line invoices and quotations', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'ONE-PAGE-INV',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Sample product', 'name_ar' => 'منتج', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $invoiceSheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $invoiceHtml = view($invoiceSheet['view'], $invoiceSheet['data'])->render();

    expect(DocumentPdfRenderer::pageCountForHtml($invoiceHtml))->toBe(1);

    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'ONE-PAGE-QUO',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(10)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $quoSheet = PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), true);
    $quoHtml = view($quoSheet['view'], $quoSheet['data'])->render();

    expect(DocumentPdfRenderer::pageCountForHtml($quoHtml))->toBe(1);
});

it('uses the SAR svg in PDF html and omits the Bangla quotation subtitle', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'QUO-SAR-BN',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $sheet = PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('invoice-sar-symbol__svg')
        ->toContain('display:inline-block!important')
        ->toContain('.invoice-a4__title-bn,.print-classic__title-bn{display:none!important}')
        ->not->toContain('display:inline!important;font-size:.85em;font-weight:700}');
});

it('preserves digit order in Arabic seller address when shaping', function () {
    $html = '<div class="invoice-print-root--pdf"><p class="invoice-a4__seller-side--ar">الرياض ، حي النخيل ، 709812629</p></div>';

    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('709812629')
        ->not->toMatch('/629\s*،\s*709812/');
});

it('renders public 404 copy from the locale cookie when session middleware did not run', function () {
    $enHeading = trans('errors.not_found_heading', [], 'en');

    $html = $this->withUnencryptedCookie(LocaleCookie::NAME, 'en')
        ->get('/this-route-does-not-exist-xyz')
        ->assertNotFound()
        ->getContent();

    expect($html)
        ->toContain($enHeading)
        ->toContain('lang="en"');
});

it('redirects standard uncleared invoice PDF download with a translated flash', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-NO-PDF',
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
        ->from(route('admin.invoices'))
        ->get(route('admin.invoices.pdf', $invoice))
        ->assertRedirect(route('admin.invoices'))
        ->assertSessionHas('error', __('invoices.pdf_not_cleared'));
});