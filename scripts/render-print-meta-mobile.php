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

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! is_file(database_path('database.sqlite'))) {
    passthru('cd /workspace && php artisan migrate --force --seed');
}

(new DemoStaffSeeder)->run();
BusinessSetting::singleton();
$owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
auth('staff')->login($owner);
$branchId = Branch::query()->first()?->id;

$quotation = Quotation::query()->create([
    'branch_id' => $branchId,
    'quotation_number' => 'HARNESS-QUO-META-'.uniqid(),
    'invoice_mode' => 'standard',
    'validity_until' => now()->addDays(30)->toDateString(),
    'payload' => [
        'payment_terms' => 'net_30',
        'buyer_name_en' => 'TaskenLab Company',
        'buyer_name_ar' => 'شركة تاسكن لاب',
        'buyer_email' => 'ops@taskenlab.test',
        'buyer_phone' => '+966501234567',
        'buyer_vat' => '314430837500003',
        'buyer_national_address' => [
            'building' => '7780',
            'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
            'district' => 'Al Murabba',
            'city' => 'Riyadh',
            'postal_code' => '12612',
            'country' => 'SA',
        ],
        'lines' => [
            ['name' => 'Sanitary inspection', 'name_ar' => 'فحص صحي', 'quantity' => 1, 'unit_price_ex_vat' => 450, 'vat_rate' => 15],
        ],
    ],
    'created_by_staff_user_id' => $owner->id,
]);

$invoice = Invoice::query()->create([
    'branch_id' => $branchId,
    'document_number' => 'HARNESS-STD-MOBILE-'.uniqid(),
    'document_type' => InvoiceDocumentType::Standard,
    'status' => InvoiceStatus::Issued,
    'issued_at' => now(),
    'supply_date' => now()->toDateString(),
    'payload' => [
        'payment_terms' => 'net_30',
        'buyer_name_en' => 'TaskenLab Company',
        'buyer_name_ar' => 'شركة تاسكن لاب',
        'buyer_email' => 'taulutwashi@gmail.com',
        'buyer_phone' => '+966560231362',
        'buyer_vat' => '314430837500003',
        'buyer_national_address' => [
            'building' => '7780',
            'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
            'district' => 'Al Murabba',
            'city' => 'Riyadh',
            'postal_code' => '12612',
            'country' => 'SA',
        ],
        'lines' => [
            ['name' => 'QA Product A', 'name_ar' => 'منتج', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
        ],
    ],
]);

foreach ([
    ['quo', PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), true)],
    ['inv', PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true)],
] as [$slug, $sheet]) {
    $html = view($sheet['view'], $sheet['data'])->render();
    $pdf = DocumentPdfRenderer::render($html);
    $pdfPath = "/opt/cursor/artifacts/{$slug}-meta-mobile.pdf";
    file_put_contents($pdfPath, $pdf);
    $pngBase = "/opt/cursor/artifacts/{$slug}-meta-mobile";
    passthru('pdftoppm -png -r 144 -f 1 -l 1 '.escapeshellarg($pdfPath).' '.escapeshellarg($pngBase));
    if (is_file($pngBase.'-1.png')) {
        rename($pngBase.'-1.png', "/opt/cursor/artifacts/{$slug}-meta-mobile-pdf.png");
    }
}

$htmlCases = [
    'quotation-html-390' => PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), false),
    'invoice-html-390' => PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), false),
];

$chrome = null;
foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome-stable', '/usr/local/bin/google-chrome'] as $candidate) {
    if (is_file($candidate)) {
        $chrome = $candidate;
        break;
    }
}

if ($chrome !== null) {
    foreach ($htmlCases as $name => $sheet) {
        $html = view($sheet['view'], $sheet['data'])->render();
        $links = '';
        foreach (['invoice-print-*.css', 'print-template-modern-*.css'] as $pattern) {
            foreach (glob(public_path('build/assets/'.$pattern)) ?: [] as $asset) {
                $links .= '<link rel="stylesheet" href="file://'.$asset.'">';
            }
        }
        foreach (glob(public_path('build/assets/invoice-print-preview-*.js')) ?: [] as $asset) {
            $links .= '<script src="file://'.$asset.'"></script>';
        }
        $html = str_replace('</head>', $links.'</head>', $html);
        $htmlPath = '/opt/cursor/artifacts/'.$name.'.html';
        file_put_contents($htmlPath, $html);
        passthru($chrome.' --headless --disable-gpu --window-size=390,2200 --screenshot=/opt/cursor/artifacts/'.$name.'.png '.escapeshellarg('file://'.$htmlPath).' 2>/dev/null');
    }
}

echo "Done.\n";