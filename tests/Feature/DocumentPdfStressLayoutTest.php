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
    $this->branchId = Branch::query()->value('id');
    BusinessSetting::singleton();
});

/**
 * @return list<array<string, mixed>>
 */
function stressPdfLines(int $count): array
{
    $lines = [];
    for ($i = 1; $i <= $count; $i++) {
        $lines[] = [
            'name' => 'QA plumbing service item '.$i.' with extra english context for taxable plumbing',
            'name_ar' => 'تسليك وتصوير تلفزيوني لتدفق الصرف الصحي مع عدة مواد كيميائية وضمان جودة العمل رقم '.$i,
            'quantity' => 1,
            'unit_price_ex_vat' => 120 + $i,
            'vat_rate' => 15,
        ];
    }

    return $lines;
}

function renderStressInvoicePdf(int $lineCount): string
{
    $invoice = Invoice::query()->create([
        'branch_id' => test()->branchId,
        'document_number' => 'STRESS-'.$lineCount.'-'.uniqid(),
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => now()->addDays(30),
        'payload' => [
            'payment_terms' => 'net_30',
            'lines' => stressPdfLines($lineCount),
            'buyer_name_en' => 'TaskenLab Company',
            'buyer_name_ar' => 'شركة تاسكن لاب للخدمات الطبية والتجارية المحدودة',
            'buyer_email' => 'taulutwashi@gmail.com',
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
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();

    return DocumentPdfRenderer::render($html);
}

it('renders eight-line stress invoice on two pages with arabic prelines', function () {
    renderStressInvoicePdf(8);
    $invoice = Invoice::query()->latest('id')->firstOrFail();
    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);
    $binary = DocumentPdfRenderer::render($html);

    expect($binary)->toStartWith('%PDF')
        ->and(DocumentPdfRenderer::pageCountInBinary($binary))->toBe(2)
        ->and(substr_count($wrapped, 'invoice-pdf-ar-preline'))->toBeGreaterThan(3);
});

it('renders twenty-line stress invoice with multiple pages', function () {
    $binary = renderStressInvoicePdf(20);

    expect(DocumentPdfRenderer::pageCountInBinary($binary))->toBeGreaterThanOrEqual(3);
});

it('embeds dompdf page labels in stress pdf text', function () {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        $this->markTestSkipped('pdftotext not installed');
    }

    $binary = renderStressInvoicePdf(8);
    $path = storage_path('app/stress-'.uniqid().'.pdf');
    file_put_contents($path, $binary);
  $text = (string) shell_exec('pdftotext '.escapeshellarg($path).' - 2>/dev/null');
    @unlink($path);

    expect($text)->toMatch('/Page\s+1\s*\/\s*2/')
        ->and($text)->toMatch('/Page\s+2\s*\/\s*2/');
});