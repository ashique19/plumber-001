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
    $wrapped = DocumentPdfRenderer::wrapHtml($html, forMpdf: true);
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
        ->toContain('invoice-a4__qr-note-ar')
        ->toContain('invoice-pdf-ar-block')
        ->toContain((string) $address)
        ->not->toContain('<colgroup')
        ->not->toContain('print-tpl-modern__totals-net');
});

it('renders mPDF binary with totals text extractable via pdftotext when available', function () {
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
        $artifactDir = '/opt/cursor/artifacts';
        if (! is_dir($artifactDir)) {
            mkdir($artifactDir, 0755, true);
        }
        $pngBase = $artifactDir.'/invoice-pdf-mpdf-bidi-fix';
        shell_exec('pdftoppm -f 1 -l 1 -png '.escapeshellarg($path).' '.escapeshellarg($pngBase).' 2>/dev/null');
        expect(is_file($pngBase.'-1.png'))->toBeTrue();
    }

    @unlink($path);
});
