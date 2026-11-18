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

it('renders a one-page mPDF for a standard invoice with a buyer block', function () {
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

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-ONE-PAGE-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'buyer_name' => 'TaskenLab Company',
            'buyer_name_ar' => 'شركة تاسكن لاب',
            'buyer_vat_number' => '314430837500003',
            'buyer_email' => 'buyer@example.test',
            'buyer_phone' => '+966 560231362',
            'buyer_address' => '7780, Prince Abdulaziz Street, Riyadh',
            'payment_terms_en' => 'Cash (due on invoice date)',
            'lines' => [
                ['name' => 'QA Product A', 'name_ar' => 'منتج اختبار أ', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect(DocumentPdfRenderer::pageCountForHtml($html))->toBe(1)
        ->and($wrapped)->toContain('invoice-national-address-ar__line')
        ->toContain('invoice-a4__buyer-table-value')
        ->toContain('font-family:dejavusans')
        ->toContain('invoice-a4__buyer-table-label')
        ->not->toContain('text-overflow:ellipsis');
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

it('keeps simplified invoices with eight lines on one page with QR beside totals', function () {
    $lines = [];
    for ($i = 1; $i <= 8; $i++) {
        $lines[] = [
            'name' => "Line item {$i}",
            'name_ar' => "بند {$i}",
            'quantity' => 1,
            'unit_price_ex_vat' => 12.5,
            'vat_rate' => 15,
        ];
    }

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'EIGHT-LINE-SIMP',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => $lines],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();

    expect($html)
        ->toContain('print-tpl-modern__closing-grid')
        ->toContain('print-tpl-modern__closing-qr');

    expect(DocumentPdfRenderer::pageCountForHtml($html))->toBe(1);
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
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('width:0.95em!important')
        ->toContain('.invoice-a4__title-bn,.print-classic__title-bn{display:none!important}')
        ->not->toContain('display:inline!important;font-size:.85em;font-weight:700}');
});

it('preserves digit order in Arabic seller address for mPDF output', function () {
    $html = '<div class="invoice-print-root--pdf"><p class="invoice-a4__seller-side--ar invoice-print-ar" dir="rtl">الرياض ، حي النخيل ، 709812629</p></div>';

    $wrapped = DocumentPdfRenderer::wrapHtml($html);
    $decoded = html_entity_decode($wrapped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($decoded)
        ->toContain('709812629')
        ->toContain('الرياض');

    $binary = DocumentPdfRenderer::render($html);
    expect($binary)->toStartWith('%PDF');
});

it('keeps TRN digits in a separate LTR run beside Arabic labels', function () {
    $html = '<div class="invoice-print-root--pdf invoice-print-ar" dir="rtl">'
        .'<p class="m-0 invoice-pdf-ar-plaintext" dir="rtl">رقم التسجيل الضريبي '
        .'<span dir="ltr" class="invoice-pdf-ltr-digits">123456789012345</span></p>'
        .'</div>';

    $wrapped = DocumentPdfRenderer::wrapHtml($html);
    $decoded = html_entity_decode($wrapped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    expect($decoded)
        ->toContain('123456789012345')
        ->toContain('رقم التسجيل الضريبي')
        ->toContain('invoice-pdf-ltr-digits');

    expect(DocumentPdfRenderer::render($html))->toStartWith('%PDF');
});

it('inlines the SAR symbol as a dompdf-friendly data URI image', function () {
    $html = '<span class="invoice-sar-symbol"><svg class="invoice-sar-symbol__svg" xmlns="http://www.w3.org/2000/svg"></svg></span>';

    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('class="invoice-sar-symbol__svg"')
        ->not->toContain('<svg');
});

it('inlines the SAR symbol as a data URI image for dompdf PDFs', function () {
    $html = '<span class="invoice-sar-symbol"><svg class="invoice-sar-symbol__svg" xmlns="http://www.w3.org/2000/svg"></svg></span>';

    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('class="invoice-sar-symbol__svg"')
        ->not->toContain('<svg');
});

it('keeps modern totals as three fixed columns in PDF overlay CSS', function () {
    $wrapped = DocumentPdfRenderer::wrapHtml('<div class="invoice-print-root--pdf"><table class="print-tpl-modern__totals invoice-print-totals"></table></div>');

    expect($wrapped)
        ->toContain('table-layout:fixed!important')
        ->toContain('invoice-print-totals__value{white-space:nowrap!important')
        ->toContain('DejaVu Sans')
        ->toContain('print-tpl-modern__totals-net td{border-top:1px solid #1a1a1a!important')
        ->toContain('invoice-print-money{display:inline!important;white-space:nowrap!important');
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