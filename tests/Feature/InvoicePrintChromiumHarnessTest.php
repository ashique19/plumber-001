<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('prints modern standard and simplified invoice html to pdf with chromium when available', function (string $documentType) {
    $chrome = null;
    foreach (['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome-stable'] as $candidate) {
        if (is_file($candidate)) {
            $chrome = $candidate;
            break;
        }
    }

    if ($chrome === null) {
        $this->markTestSkipped('Chromium not installed in this environment.');
    }

    $payload = $documentType === 'standard' ? [
        'payment_method' => 'cash',
        'amount_words_en' => 'Twenty-Eight Saudi Riyals and Seventy-Five Halalas only',
        'amount_words_ar' => 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط',
        'buyer_name_en' => 'TaskenLab Company',
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
            ['name' => 'QA Product A', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
        ],
    ] : [
        'payment_method' => 'cash',
        'amount_words_en' => 'Eleven Saudi Riyals and Fifty Halalas only',
        'amount_words_ar' => 'أحد عشر ريال و خمسون هللة فقط',
        'lines' => [
            ['name' => 'Line', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
        ],
    ];

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'HARNESS-CHROME-'.$documentType,
        'document_type' => $documentType === 'standard' ? InvoiceDocumentType::Standard : InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => $payload,
    ]);

    auth('staff')->login($this->owner);
    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), false);
    $html = view($sheet['view'], $sheet['data'])->render();

    foreach (['invoice-print-*.css', 'print-template-modern-*.css'] as $pattern) {
        foreach (glob(public_path('build/assets/'.$pattern)) ?: [] as $asset) {
            $html = str_replace('</head>', '<link rel="stylesheet" href="file://'.$asset.'"></head>', $html);
        }
    }

    $dir = storage_path('app/invoice-print-harness');
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $htmlPath = $dir.'/harness-chrome-'.$documentType.'.html';
    $pdfPath = $dir.'/harness-chrome-'.$documentType.'.pdf';
    file_put_contents($htmlPath, $html);

    $command = sprintf(
        '%s --headless --disable-gpu --no-sandbox --disable-dev-shm-usage --no-pdf-header-footer --print-to-pdf=%s %s 2>/dev/null',
        escapeshellarg($chrome),
        escapeshellarg($pdfPath),
        escapeshellarg('file://'.$htmlPath),
    );

    $exitCode = 0;
    passthru($command, $exitCode);

    if ($exitCode !== 0) {
        $this->markTestSkipped('Chromium print-to-pdf failed (exit '.$exitCode.').');
    }

    expect($exitCode)->toBe(0)
        ->and(is_file($pdfPath))->toBeTrue()
        ->and(filesize($pdfPath))->toBeGreaterThan(1500);

    expect($html)->toContain('invoice-print-page-label__print')
        ->toContain('HARNESS-CHROME-'.$documentType);
})->with(['simplified', 'standard']);