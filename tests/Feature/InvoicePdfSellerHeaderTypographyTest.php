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

/**
 * @return array{cap: int, ink: int}
 */
function pdfHeaderDigitCap(GdImage $img, int $w, int $h, float $x1Pct, float $y1Pct, float $x2Pct, float $y2Pct): array
{
    $x1 = (int) floor($w * $x1Pct);
    $x2 = (int) ceil($w * $x2Pct);
    $y1 = (int) floor($h * $y1Pct);
    $y2 = (int) ceil($h * $y2Pct);

    $minY = $y2 + 1;
    $maxY = $y1 - 1;
    $ink = 0;
    for ($y = $y1; $y <= $y2; $y++) {
        for ($x = $x1; $x <= $x2; $x++) {
            $rgb = imagecolorat($img, $x, $y);
            $lum = (int) (0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            if ($lum < 175) {
                $ink++;
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }
    }

    if ($ink === 0) {
        return ['cap' => 0, 'ink' => 0];
    }

    return ['cap' => $maxY - $minY + 1, 'ink' => $ink];
}

it('keeps seller header body digit caps within one pixel at 144 dpi', function (string $documentType) {
    if (! extension_loaded('gd') || trim((string) shell_exec('command -v pdftoppm')) === '') {
        $this->markTestSkipped('GD and pdftoppm required');
    }

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'HDR-TYPE-'.$documentType.'-'.uniqid(),
        'document_type' => $documentType === 'standard' ? InvoiceDocumentType::Standard : InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'QA Product A', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15]],
            'buyer_phone' => '+966568729261',
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $binary = DocumentPdfRenderer::render(view($sheet['view'], $sheet['data'])->render());
    $pdf = tempnam(sys_get_temp_dir(), 'pdf-hdr-');
    file_put_contents($pdf, $binary);
    $prefix = tempnam(sys_get_temp_dir(), 'png-hdr-');
    @unlink($prefix);
    exec('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdf).' '.escapeshellarg($prefix));
    @unlink($pdf);
    $png = $prefix.'.png';
    $img = imagecreatefrompng($png);
    $w = imagesx($img);
    $h = imagesy($img);

    $caps = [];
    for ($y1 = 0.150; $y1 <= 0.195; $y1 += 0.006) {
        $y2 = $y1 + 0.006;
        foreach ([[0.12, 0.36], [0.64, 0.95]] as [$x1, $x2]) {
            $sample = pdfHeaderDigitCap($img, $w, $h, $x1, $y1, $x2, $y2);
            if ($sample['ink'] > 30 && $sample['cap'] >= 8 && $sample['cap'] <= 14) {
                $caps[] = $sample['cap'];
            }
        }
    }
    $caps = array_values(array_unique(array_filter($caps, static fn (int $c): bool => $c >= 11)));

    if ($documentType === 'simplified') {
        $artifactDir = '/opt/cursor/artifacts';
        if (! is_dir($artifactDir)) {
            @mkdir($artifactDir, 0755, true);
        }
        $cropTop = (int) floor($h * 0.10);
        $cropH = (int) ceil($h * 0.16);
        $cropImg = imagecreatefrompng($png);
        $crop = imagecreatetruecolor($w, $cropH);
        imagecopy($crop, $cropImg, 0, 0, 0, $cropTop, $w, $cropH);
        imagepng($crop, $artifactDir.'/pdf-seller-header-144dpi-crop.png');
        imagedestroy($cropImg);
        imagedestroy($crop);
    }

    imagedestroy($img);
    @unlink($png);

    $caps = array_values(array_filter($caps, static fn (int $c): bool => $c >= 8));

    expect($caps)->not->toBeEmpty();
    expect(max($caps) - min($caps))->toBeLessThanOrEqual(1, 'header digit cap heights must match within 1px; caps='.json_encode($caps));
})->with(['simplified', 'standard'])->group('pdf-visual');