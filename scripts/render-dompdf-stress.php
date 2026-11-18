<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use App\Support\Invoicing\ZatcaPhase1QrEncoder;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

(new DemoStaffSeeder)->run();
$business = BusinessSetting::singleton();
$settings = $business->settings;
data_set($settings, 'legal_entity.name_en', 'RAZAN HADI ALHAYANI Establishment');
data_set($settings, 'legal_entity.name_ar', 'مؤسسة رزان هادي الحياني');
$business->settings = $settings;
$business->save();

$branchId = Branch::query()->value('id');
$trn = (string) data_get($settings, 'legal_entity.vat_number', '311122393500004');
$qr = ZatcaPhase1QrEncoder::encodeBase64(
    sellerName: (string) data_get($settings, 'legal_entity.name_ar'),
    vatNumber: $trn,
    timestampIso8601: now('Asia/Riyadh')->format('Y-m-d\TH:i:sP'),
    invoiceTotalIncVat: '3795.01',
    vatTotal: '495.01',
);

$longAr = 'تسليك وتصوير تلفزيوني لتدفق الصرف الصحي مع عدة مواد كيميائية وضمان جودة العمل';

$cases = [8 => 'dompdf-stress-8', 20 => 'dompdf-stress-20'];

foreach ($cases as $count => $slug) {
    $lines = [];
    for ($i = 1; $i <= $count; $i++) {
        $lines[] = [
            'name' => 'QA plumbing service item '.$i,
            'name_ar' => $longAr.' رقم '.$i,
            'quantity' => 1,
            'unit_price_ex_vat' => 350 + $i,
            'vat_rate' => 15,
        ];
    }

    $invoice = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => 'HARNESS-STRESS-'.$count.'-'.uniqid(),
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => now()->addDays(30),
        'qr_tlv_base64' => $qr,
        'payload' => [
            'payment_terms' => 'net_30',
            'lines' => $lines,
            'buyer_name_en' => 'TaskenLab Company',
            'buyer_name_ar' => 'شركة تاسكن لاب للخدمات الطبية والتجارية المحدودة',
            'buyer_vat' => '314430837500003',
            'buyer_national_address' => [
                'building' => '7780',
                'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
                'district' => 'Al Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12612',
                'country' => 'SA',
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $binary = DocumentPdfRenderer::render($html);
    $pdfPath = "/opt/cursor/artifacts/{$slug}.pdf";
    file_put_contents($pdfPath, $binary);
    $prefix = "/opt/cursor/artifacts/{$slug}";
    passthru('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdfPath).' '.escapeshellarg($prefix).' 2>/dev/null');

    echo $slug.': pages='.DocumentPdfRenderer::pageCountInBinary($binary)
        .' png='.(is_file($prefix.'.png') ? 'yes' : 'no')."\n";
}