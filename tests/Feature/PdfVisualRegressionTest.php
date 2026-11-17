<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    (new Database\Seeders\DemoStaffSeeder)->run();
    BusinessSetting::singleton();
});

/**
 * @return list<array{slug: string, binary: string}>
 */
function pdfRegressionSnapshots(): array
{
    $branchId = Branch::query()->value('id');
    $baseLine = [
        'name' => 'QA Product D',
        'name_ar' => 'منتج تجريبي',
        'quantity' => 1,
        'unit_price_ex_vat' => 30,
        'vat_rate' => 15,
    ];

    $simplified = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => 'SIMP-PDF-VIS-001',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [$baseLine], 'buyer_phone' => '+966560231366'],
    ]);

    $standard = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => 'STD-PDF-VIS-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [$baseLine],
            'buyer_name' => 'washi',
            'buyer_phone' => '+966560231366',
        ],
    ]);

    $credit = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => 'CN-PDF-VIS-001',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [$baseLine]],
    ]);

    $debit = Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => 'DN-PDF-VIS-001',
        'document_type' => InvoiceDocumentType::DebitNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [$baseLine]],
    ]);

    $quotation = Quotation::query()->create([
        'branch_id' => $branchId,
        'quotation_number' => 'QUO-PDF-VIS-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(14)->toDateString(),
        'payload' => ['lines' => [$baseLine]],
        'created_by_staff_user_id' => \App\Models\StaffUser::query()->value('id'),
    ]);

    $cases = [];
    foreach ([
        ['simplified-modern', $simplified, 'modern'],
        ['standard-modern', $standard, 'modern'],
        ['simplified-classic', $simplified, 'classic'],
        ['credit-modern', $credit, 'modern'],
        ['debit-modern', $debit, 'modern'],
    ] as [$slug, $invoice, $template]) {
        $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => $template]), true);
        $html = view($sheet['view'], $sheet['data'])->render();
        $cases[] = ['slug' => $slug, 'binary' => DocumentPdfRenderer::render($html)];
    }

    $quoSheet = PrintSheetFactory::quotation($quotation, Request::create('/', 'GET', ['template' => 'modern']), true);
    $quoHtml = view($quoSheet['view'], $quoSheet['data'])->render();
    $cases[] = ['slug' => 'quotation-modern', 'binary' => DocumentPdfRenderer::render($quoHtml)];

    return $cases;
}

it('renders PDFs via mPDF with Arabic layout overlays and SAR symbols', function () {
    foreach (pdfRegressionSnapshots() as $case) {
        expect($case['binary'])
            ->toStartWith('%PDF')
            ->and(strlen($case['binary']))->toBeGreaterThan(8000)
            ->and($case['binary'])->not->toContain('???');
    }
});

it('exports PDF regression PNGs when pdftoppm is available', function () {
    if (trim((string) shell_exec('command -v pdftoppm')) === '') {
        $this->markTestSkipped('pdftoppm is not installed');
    }

    $artifactRoot = '/opt/cursor/artifacts/pdf-regression';
    if (! is_dir($artifactRoot) && ! @mkdir($artifactRoot, 0775, true) && ! is_dir($artifactRoot)) {
        $artifactRoot = storage_path('app/pdf-regression');
        File::ensureDirectoryExists($artifactRoot);
    }

    foreach (pdfRegressionSnapshots() as $case) {
        $pdfPath = $artifactRoot.'/'.$case['slug'].'.pdf';
        file_put_contents($pdfPath, $case['binary']);

        $prefix = $artifactRoot.'/'.$case['slug'];
        $code = 0;
        exec('pdftoppm -png -r 144 -singlefile '.escapeshellarg($pdfPath).' '.escapeshellarg($prefix), $output, $code);

        expect($code)->toBe(0, 'pdftoppm failed for '.$case['slug'])
            ->and(is_file($prefix.'.png'))->toBeTrue();
    }
})->group('pdf-visual');