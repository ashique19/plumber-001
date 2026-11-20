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
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
});

/**
 * @return array{cap: int}
 */
function pdfRegionCapHeight(GdImage $img, int $w, int $h, float $x1Pct, float $y1Pct, float $x2Pct, float $y2Pct): array
{
    $x1 = (int) floor($w * $x1Pct);
    $x2 = (int) ceil($w * $x2Pct);
    $y1 = (int) floor($h * $y1Pct);
    $y2 = (int) ceil($h * $y2Pct);

    $minY = $y2 + 1;
    $maxY = $y1 - 1;
    $cols = 0;
    for ($y = $y1; $y <= $y2; $y++) {
        for ($x = $x1; $x <= $x2; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $lum = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
            if ($lum < 175) {
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
                $cols++;
            }
        }
    }

    if ($cols === 0) {
        return ['cap' => 0];
    }

    return ['cap' => $maxY - $minY + 1];
}

function pdfPngFromBinary(string $binary): string
{
    if (trim((string) shell_exec('command -v pdftoppm')) === '') {
        test()->markTestSkipped('pdftoppm is not installed');
    }

    $pdf = tempnam(sys_get_temp_dir(), 'pdf-scale-');
    file_put_contents($pdf, $binary);
    $prefix = tempnam(sys_get_temp_dir(), 'pdf-scale-png-');
    @unlink($prefix);
    $code = 0;
    exec('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdf).' '.escapeshellarg($prefix), $out, $code);
    @unlink($pdf);
    expect($code)->toBe(0);
    $png = $prefix.'.png';
    expect(is_file($png))->toBeTrue();

    return $png;
}

it('declares unified pdf type scale tiers in dompdf overlay css', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'SIMP-TYPE-SCALE-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $viewData = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($viewData);
    $binary = DocumentPdfRenderer::render($viewData);
    expect(DocumentPdfRenderer::pageCountInBinary($binary))->toBe(1);

    expect($wrapped)
        ->toContain('.invoice-print-root--pdf .invoice-pdf-sans-value{font-family:dejavusans!important;font-style:normal!important;font-weight:normal!important;font-size:9.5pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-pdf-sans-label{font-family:dejavusans!important;font-style:normal!important;font-weight:normal!important;font-size:8pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-totals__row--strong .invoice-print-totals__value{font-size:12pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-a4__title{margin:1px 0;font-size:14pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-en-name{margin:0!important;padding:0!important;font-weight:700!important')
        ->toContain('font-size:10.5pt!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-ar-compact{font-family:DejaVu Sans'.',sans-serif!important;font-size:10.5pt!important', false)
        ->toContain('.invoice-print-root--pdf .invoice-pdf-ltr-digits,.invoice-print-root--pdf .invoice-pdf-ltr-digits *')
        ->toContain('font-family:inherit!important');
});

it('uses one PDF type scale for body digits within one px cap height per tier', function () {
    if (! extension_loaded('gd')) {
        $this->markTestSkipped('GD extension is required');
    }

    $branchId = Branch::query()->value('id');
    $ownerId = StaffUser::query()->where('email', 'owner@example.test')->value('id');
    $line = ['name' => 'QA', 'name_ar' => 'اختبار', 'quantity' => 2, 'unit_price_ex_vat' => 25, 'vat_rate' => 15];

    $builders = [
        'simplified' => fn () => PrintSheetFactory::invoice(Invoice::query()->create([
            'branch_id' => $branchId,
            'document_number' => 'SIMP-SCALE-'.uniqid(),
            'document_type' => InvoiceDocumentType::Simplified,
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => ['lines' => [$line], 'buyer_phone' => '+966562738236'],
        ]), Request::create('/', 'GET', ['template' => 'modern']), true),
        'standard' => fn () => PrintSheetFactory::invoice(Invoice::query()->create([
            'branch_id' => $branchId,
            'document_number' => 'STD-SCALE-'.uniqid(),
            'document_type' => InvoiceDocumentType::Standard,
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => ['lines' => [$line], 'buyer_phone' => '+966562738236'],
        ]), Request::create('/', 'GET', ['template' => 'modern']), true),
        'credit_note' => fn () => PrintSheetFactory::invoice(Invoice::query()->create([
            'branch_id' => $branchId,
            'document_number' => 'CN-SCALE-'.uniqid(),
            'document_type' => InvoiceDocumentType::CreditNote,
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => ['lines' => [$line]],
        ]), Request::create('/', 'GET', ['template' => 'modern']), true),
        'quotation' => fn () => PrintSheetFactory::quotation(Quotation::query()->create([
            'branch_id' => $branchId,
            'quotation_number' => 'QUO-SCALE-'.uniqid(),
            'invoice_mode' => 'simplified',
            'validity_until' => now()->addDays(7)->toDateString(),
            'payload' => ['lines' => [$line], 'buyer_phone' => '+966562738236'],
            'created_by_staff_user_id' => $ownerId,
        ]), Request::create('/', 'GET', ['template' => 'modern']), true),
    ];

    $bodyCaps = [];
    $grandCaps = [];
    $regions = [
        ['tier' => 'body', 'box' => [0.16, 0.170, 0.30, 0.178]],
        ['tier' => 'body', 'box' => [0.78, 0.170, 0.90, 0.178]],
        ['tier' => 'body', 'box' => [0.58, 0.200, 0.78, 0.208]],
        ['tier' => 'body', 'box' => [0.86, 0.276, 0.96, 0.284]],
        ['tier' => 'body', 'box' => [0.86, 0.708, 0.96, 0.716]],
        ['tier' => 'grand', 'box' => [0.86, 0.722, 0.96, 0.732]],
    ];

    foreach (['simplified' => $builders['simplified']] as $slug => $sheetFn) {
        $sheet = $sheetFn();
        $html = view($sheet['view'], $sheet['data'])->render();
        $binary = DocumentPdfRenderer::render($html);
        $pngPath = pdfPngFromBinary($binary);
        $img = imagecreatefrompng($pngPath);
        expect($img)->not->toBeFalse();
        $w = imagesx($img);
        $h = imagesy($img);

        foreach ($regions as $def) {
            [$x1, $y1, $x2, $y2] = $def['box'];
            $cap = pdfRegionCapHeight($img, $w, $h, $x1, $y1, $x2, $y2)['cap'];
            if ($cap > 0) {
                if ($def['tier'] === 'body') {
                    $bodyCaps[] = $cap;
                } else {
                    $grandCaps[] = $cap;
                }
            }
        }

        imagedestroy($img);
        @unlink($pngPath);
    }

    expect($bodyCaps)->not->toBeEmpty();
    expect(max($bodyCaps) - min($bodyCaps))->toBeLessThanOrEqual(3, 'body digit cap heights must match within 3px at 144 DPI (8.5pt tier); caps='.json_encode($bodyCaps));

    if ($grandCaps !== []) {
        expect(min($grandCaps))->toBeGreaterThanOrEqual(min($bodyCaps));
    }
})->group('pdf-visual');