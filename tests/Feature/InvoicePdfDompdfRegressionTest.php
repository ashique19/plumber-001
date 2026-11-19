<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\BusinessProfile;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
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

    $this->branchId = Branch::query()->value('id');
});

it('includes totals rows, QR image, and Arabic address in wrapped PDF html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PDF-REG-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);
    $address = BusinessProfile::formatNationalAddressAr([
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
    ]);

    expect($wrapped)
        ->toContain('invoice-print-totals__en')
        ->toContain('Subtotal')
        ->toContain('Total VAT')
        ->toContain('invoice-a4__qr-img')
        ->toContain('invoice-a4__qr-note-ar__line')
        ->toContain('invoice-a4__qr-note-lines')
        ->toContain('This QR code is encoded as per')
        ->toContain('ZATCA e-invoicing requirements')
        ->toContain('invoice-a4__qr-note-ar-lines')
        ->toContain('invoice-pdf-ar-block')
        ->toContain('invoice-print-totals__ar--nowrap')
        ->toContain('invoice-national-address-ar--pdf')
        ->toContain('invoice-pdf-ar-address-line')
        ->toContain('invoice-pdf-ltr-digits')
        ->toContain('7098')
        ->toContain('invoice-national-address-ar__line')
        ->toContain('data:image/svg+xml;base64,')
        ->not->toContain('Issue date &amp; time')
        ->toContain('invoice-print-info-grid__col-en', false)
        ->not->toMatch('/class="[^"]*print-tpl-modern__totals-net/');
});

it('renders standard buyer address as explicit lines with sans buyer labels in pdf html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-PDF-BUY-'.uniqid(),
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'buyer_name_en' => 'TaskenLab Company',
            'buyer_name_ar' => 'شركة تاسكنلاب',
            'buyer_national_address' => [
                'building' => '7780',
                'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
                'district' => 'Al Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12612',
                'additional' => '3841',
                'country' => 'SA',
            ],
            'lines' => [
                ['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('invoice-a4__buyer-address-en__line')
        ->toContain('invoice-a4__buyer-name-cell')
        ->toContain('Prince Abdulaziz bin Musaed bin Jalawi Street')
        ->toContain('Al Murabba, Riyadh 12612')
        ->toContain('invoice-print-seller-header-ids__label')
        ->toContain('invoice-pdf-sans-value')
        ->toContain('DejaVu Sans')
        ->not->toContain('invoice-a4__buyer-address-ar');
});

it('uses full arabic payment label on simplified modern intro grid in pdf html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PAY-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();

    expect($html)->toContain('شروط الدفع');
});

it('keeps long invoice numbers on one nowrap line in pdf html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-20261118-182356',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->toContain('SIMP-20261118-182356')
        ->toContain('invoice-print-info-grid__value--nowrap')
        ->toContain('white-space:nowrap!important', false);
});

it('limits standard invoice arabic amount words to two pdf lines', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-WORDS-WRAP',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'QA Product A', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    $wordsBlockStart = strpos($wrapped, 'invoice-print-words-block__ar');
    expect($wordsBlockStart)->toBeInt();

    $segment = substr($wrapped, $wordsBlockStart, 2500) ?: '';
    preg_match_all('/<div class="[^"]*invoice-pdf-ar-preline/', $segment, $wordLineMatches);
    expect(count($wordLineMatches[0]))->toBeLessThanOrEqual(2);
});

it('paints page x of y in dompdf output when pdftotext is available', function () {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        $this->markTestSkipped('pdftotext not installed');
    }

    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'theme.logo_url', 'images/plumber/hero.webp');
    $business->settings = $settings;
    $business->save();

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PAGE-FOOT-001',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $binary = DocumentPdfRenderer::render(view($sheet['view'], $sheet['data'])->render());
    $path = storage_path('app/pdf-page-footer-'.uniqid().'.pdf');
    file_put_contents($path, $binary);
    $text = (string) shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null');
    @unlink($path);

    expect($text)->toMatch('/Page\s+1\s*\/\s*1/');
});

it('renders dompdf harness pdfs with logo for simplified and standard', function () {
    if (trim((string) shell_exec('command -v pdftoppm')) === '') {
        $this->markTestSkipped('pdftoppm not installed');
    }

    $artifactDir = \Tests\Support\PdfTestArtifacts::directory();
    if ($artifactDir === null) {
        $this->markTestSkipped('PdfTestArtifacts directory unavailable');
    }

    $dbPath = storage_path('app/dompdf-harness-'.uniqid('', true).'.sqlite');
    $cmd = sprintf(
        'DB_CONNECTION=sqlite DB_DATABASE=%s php %s 2>&1',
        escapeshellarg($dbPath),
        escapeshellarg(base_path('scripts/render-dompdf-harness.php')),
    );
    $exit = 0;
    passthru($cmd, $exit);
    @unlink($dbPath);
    expect($exit)->toBe(0);

    foreach (['dompdf-render-simplified', 'dompdf-render-standard'] as $slug) {
        expect(is_file("{$artifactDir}/{$slug}.pdf"))->toBeTrue("{$slug}.pdf missing");
        expect(is_file("{$artifactDir}/{$slug}.png"))->toBeTrue("{$slug}.png missing");
    }
});

it('renders dompdf binary with totals text extractable via pdftotext when available', function () {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        $this->markTestSkipped('pdftotext not installed');
    }

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-PDF-BIN-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $binary = DocumentPdfRenderer::render($html);
    $path = storage_path('app/pdf-regression-'.uniqid().'.pdf');
    file_put_contents($path, $binary);
    $text = (string) shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null');

    expect(strlen($binary))->toBeGreaterThan(10_000)
        ->and($text)->toMatch('/Subtotal|Total VAT|Total/i');

    if (trim((string) shell_exec('command -v pdftoppm')) !== '') {
        $artifactDir = \Tests\Support\PdfTestArtifacts::directory();
        if ($artifactDir !== null) {
            $pngBase = $artifactDir.'/invoice-pdf-address-lines';
            shell_exec('pdftoppm -f 1 -l 1 -png '.escapeshellarg($path).' '.escapeshellarg($pngBase).' 2>/dev/null');
            expect(is_file($pngBase.'-1.png'))->toBeTrue();
        }
    }

    @unlink($path);
});
