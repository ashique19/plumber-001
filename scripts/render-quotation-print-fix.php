<?php

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

(new DemoStaffSeeder)->run();
BusinessSetting::singleton();

$owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
auth('staff')->login($owner);

$quotation = Quotation::query()->create([
    'branch_id' => Branch::query()->first()?->id,
    'quotation_number' => 'HARNESS-QUO-'.uniqid(),
    'invoice_mode' => 'standard',
    'validity_until' => now()->addDays(30)->toDateString(),
    'payload' => [
        'payment_terms' => 'net_30',
        'customer' => [
            'name_en' => 'TaskenLab Company',
            'name_ar' => 'شركة تاسكن لاب',
            'address_en' => '7098, Ahmad Ibn Al Hussain Ibn Shuqair St, Al Malaz, Riyadh 12629',
            'email' => 'ops@taskenlab.test',
            'phone' => '+966501234567',
            'vat_number' => '310223344500003',
        ],
        'lines' => [
            ['name' => 'Sanitary inspection', 'name_ar' => 'فحص صحي', 'quantity' => 2, 'unit_price_ex_vat' => 450, 'vat_rate' => 15],
        ],
    ],
    'created_by_staff_user_id' => $owner->id,
]);

$request = Request::create('/', 'GET', ['template' => 'modern', 'locale' => 'en']);
$sheet = PrintSheetFactory::quotation($quotation, $request, true);
$html = view($sheet['view'], $sheet['data'])->render();
$htmlPath = '/opt/cursor/artifacts/quotation-print-modern.html';
file_put_contents($htmlPath, $html);
echo "Wrote {$htmlPath}\n";

$pdf = DocumentPdfRenderer::render($html);
$pdfPath = '/opt/cursor/artifacts/quotation-modern.pdf';
file_put_contents($pdfPath, $pdf);
echo "Wrote {$pdfPath}\n";

$pngBase = '/opt/cursor/artifacts/quotation-modern-pdf';
passthru('pdftoppm -png -r 144 -f 1 -l 1 '.escapeshellarg($pdfPath).' '.escapeshellarg($pngBase).' 2>&1', $exit);
if ($exit === 0 && is_file($pngBase.'-1.png')) {
    rename($pngBase.'-1.png', '/opt/cursor/artifacts/quotation-modern-pdf-meta.png');
}

$htmlPng = '/opt/cursor/artifacts/quotation-modern-html-print.png';
$chrome = null;
foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'] as $candidate) {
    if (is_file($candidate)) {
        $chrome = $candidate;
        break;
    }
}
if ($chrome !== null) {
    passthru($chrome.' --headless --disable-gpu --window-size=900,1280 --screenshot='.escapeshellarg($htmlPng).' '.escapeshellarg('file://'.$htmlPath).' 2>&1');
}

// Invoice PDF for QR Arabic caption (2 lines)
$invoice = \App\Models\Invoice::query()->create([
    'branch_id' => Branch::query()->first()?->id,
    'document_number' => 'HARNESS-STD-QR-'.uniqid(),
    'document_type' => \App\Enums\InvoiceDocumentType::Standard,
    'status' => \App\Enums\InvoiceStatus::Issued,
    'issued_at' => now(),
    'payload' => [
        'lines' => [
            ['name' => 'Inspection', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
        ],
    ],
]);
$invSheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
$invPdf = DocumentPdfRenderer::render(view($invSheet['view'], $invSheet['data'])->render());
$invPdfPath = '/opt/cursor/artifacts/invoice-qr-caption.pdf';
file_put_contents($invPdfPath, $invPdf);
$qrPngBase = '/opt/cursor/artifacts/invoice-qr-caption';
passthru('pdftoppm -png -r 200 -f 1 -l 1 '.escapeshellarg($invPdfPath).' '.escapeshellarg($qrPngBase).' 2>&1', $exit2);
if ($exit2 === 0 && is_file($qrPngBase.'-1.png')) {
    rename($qrPngBase.'-1.png', '/opt/cursor/artifacts/invoice-qr-caption.png');
}

echo "Done.\n";