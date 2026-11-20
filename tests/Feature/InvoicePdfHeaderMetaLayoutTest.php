<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'legal_entity.name_en', 'RAZAN HADI ALHAYANI Establishment');
    data_set($settings, 'legal_entity.name_ar', 'مؤسسة رزان هادي الحياني');
    data_set($settings, 'legal_entity.national_address', [
        'building' => '7098',
        'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district' => 'Al Malaz',
        'district_ar' => 'حي الملز',
        'city' => 'Riyadh',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
        'additional' => '3652',
    ]);
    data_set($settings, 'theme.logo_url', 'images/plumber/hero.webp');
    $business->settings = $settings;
    $business->save();
});

function pdfInkInBox(GdImage $img, int $w, int $h, float $x1Pct, float $y1Pct, float $x2Pct, float $y2Pct): int
{
    $x1 = (int) floor($w * $x1Pct);
    $x2 = (int) ceil($w * $x2Pct);
    $y1 = (int) floor($h * $y1Pct);
    $y2 = (int) ceil($h * $y2Pct);
    $count = 0;
    for ($y = $y1; $y <= $y2; $y++) {
        for ($x = $x1; $x <= $x2; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $lum = (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            if ($lum < 175) {
                $count++;
            }
        }
    }

    return $count;
}

it('avoids english seller text bleeding into the logo gutter at 144 dpi', function () {
    if (! extension_loaded('gd') || trim((string) shell_exec('command -v pdftoppm')) === '') {
        $this->markTestSkipped('GD and pdftoppm required');
    }

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'V221-SIMP-PHONE-'.now()->format('YmdHis'),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'Test Modal Product', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
            'buyer_phone' => '+966562738236',
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $binary = DocumentPdfRenderer::render(view($sheet['view'], $sheet['data'])->render());
    $pdf = tempnam(sys_get_temp_dir(), 'pdf-overlap-');
    file_put_contents($pdf, $binary);
    $prefix = tempnam(sys_get_temp_dir(), 'png-overlap-');
    @unlink($prefix);
    exec('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdf).' '.escapeshellarg($prefix));
    @unlink($pdf);
    $png = $prefix.'.png';
    $img = imagecreatefrompng($png);
    expect($img)->not->toBeFalse();
    $w = imagesx($img);
    $h = imagesy($img);

    $maxEnInkX = 0;
    $logoLaneInk = 0;
    $logoLaneX1 = (int) floor($w * 0.398);
    $logoLaneX2 = (int) ceil($w * 0.545);
    for ($y = (int) floor($h * 0.10); $y <= (int) ceil($h * 0.22); $y++) {
        for ($x = (int) floor($w * 0.03); $x <= (int) ceil($w * 0.395); $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $lum = (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            if ($lum < 175) {
                $maxEnInkX = max($maxEnInkX, $x);
            }
        }
        for ($x = $logoLaneX1; $x <= $logoLaneX2; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $lum = (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            if ($lum < 175) {
                $logoLaneInk++;
            }
        }
    }

    expect($maxEnInkX)->toBeGreaterThan(0)
        ->and($maxEnInkX)->toBeLessThan($logoLaneX1, 'english seller text must end before logo lane')
        ->and($logoLaneInk)->toBeGreaterThan(80, 'logo lane should contain the image');

    imagedestroy($img);
    @unlink($png);
})->group('pdf-visual');

it('declares pdf-only seller wrap and meta column gaps in overlay css', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'WRAP-CSS-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-line{display:block!important;margin:0!important;padding:0!important;overflow:visible!important;white-space:normal!important')
        ->toContain('.invoice-print-root--pdf .invoice-a4__header-cell--logo{width:20%!important')
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-en-name{margin:0!important;padding:0!important;font-weight:700!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important;font-family:dejavusans!important;font-size:9.5pt!important;line-height:11pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-pdf-seller-en-body{margin:0!important;padding:0!important;font-weight:normal!important;color:#111!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important;line-height:9.75pt!important;font-family:dejavusans!important;font-size:8.5pt!important')
        ->toContain('.invoice-print-root--pdf .invoice-a4__header .invoice-pdf-ltr-digits,.invoice-print-root--pdf .invoice-a4__header .invoice-print-ltr-num')
        ->toContain('font-size:inherit!important;font-family:inherit!important;font-weight:inherit!important;color:inherit!important;line-height:inherit!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__value')
        ->toContain('padding:1px 10px 1px 2px!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__ar')
        ->toContain('padding:1px 2px 1px 10px!important');
});