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
data_set($settings, 'legal_entity.national_address', [
    'building' => '7098',
    'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
    'district' => 'Al Malaz',
    'city' => 'Riyadh',
    'postal_code' => '12629',
    'additional' => '3652',
    'country' => 'SA',
    'street_ar' => 'أحمد بن الحسين بن شقير',
    'district_ar' => 'حي الملز',
    'city_ar' => 'الرياض',
]);
data_set($settings, 'theme.logo_url', 'images/plumber/hero.webp');
$business->settings = $settings;
$business->save();

$branchId = Branch::query()->value('id');
$trn = (string) data_get($settings, 'legal_entity.vat_number', '311122393500004');
$standardQrTlv = ZatcaPhase1QrEncoder::encodeBase64(
    sellerName: (string) data_get($settings, 'legal_entity.name_ar', 'مؤسسة رزان هادي الحياني'),
    vatNumber: $trn,
    timestampIso8601: now('Asia/Riyadh')->format('Y-m-d\TH:i:sP'),
    invoiceTotalIncVat: '28.75',
    vatTotal: '3.75',
);

$artifactDir = is_dir('/opt/cursor/artifacts') && is_writable('/opt/cursor/artifacts')
    ? '/opt/cursor/artifacts'
  : storage_path('framework/testing/pdf-artifacts');
if (! is_dir($artifactDir)) {
    mkdir($artifactDir, 0755, true);
}

$cases = [
    'dompdf-render-simplified' => [
        'type' => InvoiceDocumentType::Simplified,
        'number' => 'SIMP-HARNESS-'.uniqid(),
        'payload' => [
            'lines' => [
                ['name' => 'Test Modal Product', 'name_ar' => 'منتج اختبار', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ],
    'dompdf-render-credit-note' => [
        'type' => InvoiceDocumentType::CreditNote,
        'number' => 'CN-HARNESS-'.uniqid(),
        'payload' => [
            'lines' => [
                ['name' => 'Credit line', 'name_ar' => 'بند دائن', 'quantity' => 1, 'unit_price_ex_vat' => -10, 'vat_rate' => 15],
            ],
        ],
    ],
    'dompdf-render-standard' => [
        'type' => InvoiceDocumentType::Standard,
        'number' => 'STD-HARNESS-'.uniqid(),
        'due_date' => now()->addDays(30),
        'qr_tlv_base64' => $standardQrTlv,
        'payload' => [
            'payment_terms' => 'net_30',
            'zheart_reporting_status' => 'cleared',
            'lines' => [
                ['name' => 'QA Product A', 'name_ar' => 'منتج اختبار أ', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
            'buyer_name_en' => 'TaskenLab Company',
            'buyer_name_ar' => 'شركة تاسكنلاب',
            'buyer_email' => 'taulutwashi@gmail.com',
            'buyer_phone' => '+966560231362',
            'buyer_vat' => '314430837500003',
            'buyer_national_address' => [
                'building' => '7780',
                'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
                'district' => 'Al Murabba',
                'city' => 'Riyadh',
                'postal_code' => '12612',
                'additional' => '3841',
                'country' => 'SA',
            ],
        ],
    ],
];

$ownerId = StaffUser::query()->where('email', 'owner@example.test')->value('id');

foreach ($cases as $slug => $case) {
    $invoice = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => $case['number'],
        'document_type' => $case['type'],
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => $case['due_date'] ?? null,
        'qr_tlv_base64' => $case['qr_tlv_base64'] ?? null,
        'payload' => $case['payload'],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $binary = DocumentPdfRenderer::render($html);
    $pdfPath = "{$artifactDir}/{$slug}.pdf";
    file_put_contents($pdfPath, $binary);

    $prefix = "{$artifactDir}/{$slug}";
    passthru('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdfPath).' '.escapeshellarg($prefix));

    echo $slug.': pages='.DocumentPdfRenderer::pageCountInBinary($binary)
        .' producer='.(DocumentPdfRenderer::pdfProducerLine($binary) ?? '?')
        .' png='.(is_file($prefix.'.png') ? 'yes' : 'no')."\n";
}

$quotation = Quotation::query()->create([
    'branch_id' => $branchId,
    'quotation_number' => 'HARNESS-QUO-'.uniqid(),
    'invoice_mode' => 'standard',
    'validity_until' => now()->addDays(14)->toDateString(),
    'payload' => [
        'lines' => [
            ['name' => 'QA Product A', 'name_ar' => 'منتج اختبار أ', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
        ],
        'buyer_name_en' => 'TaskenLab Company',
        'buyer_name_ar' => 'شركة تاسكنلاب',
    ],
    'created_by_staff_user_id' => $ownerId,
]);
$quoSheet = PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), true);
$quoHtml = view($quoSheet['view'], $quoSheet['data'])->render();
$quoBinary = DocumentPdfRenderer::render($quoHtml);
$quoSlug = 'dompdf-render-quotation';
$quoPdf = "{$artifactDir}/{$quoSlug}.pdf";
file_put_contents($quoPdf, $quoBinary);
$quoPrefix = "{$artifactDir}/{$quoSlug}";
passthru('pdftoppm -png -r 144 -singlefile '.escapeshellarg($quoPdf).' '.escapeshellarg($quoPrefix));
echo $quoSlug.': pages='.DocumentPdfRenderer::pageCountInBinary($quoBinary)
    .' png='.(is_file($quoPrefix.'.png') ? 'yes' : 'no')."\n";