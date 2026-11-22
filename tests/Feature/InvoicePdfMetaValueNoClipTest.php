<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    app()->setLocale('en');
    (new DemoStaffSeeder)->run();
});

it('declares pdf meta values as visible and wrappable in overlay css', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'META-WRAP-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-value{width:25%!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-ar{width:15%!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__value{display:table-cell!important;text-align:left!important')
        ->toContain('overflow:visible!important;white-space:normal!important;overflow-wrap:normal!important;word-break:normal!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__value--nowrap')
        ->toContain('overflow:visible!important')
        ->toContain('border-collapse:collapse!important')
        ->toContain('border:0.5pt solid #1a1a1a!important')
        ->not->toContain('invoice-print-info-grid__pair')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__ar{display:table-cell!important;text-align:right!important')
        ->toContain('.invoice-print-root--pdf .invoice-print-info-grid__col-en{width:10%!important');
});

it('renders full long meta values in pdf text extract', function () {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        $this->markTestSkipped('pdftotext required');
    }

    $docNo = 'V221-SIMP-PHONE-20261120183431';
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => $docNo,
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'Test', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
            'buyer_phone' => '+966562738236',
            'payment_terms' => 'cash',
        ],
    ]);

    $sheet = PrintSheetFactory::invoice(
        $invoice,
        Request::create('/', 'GET', ['template' => 'modern', 'locale' => 'en']),
        true,
    );
    $binary = DocumentPdfRenderer::render(view($sheet['view'], $sheet['data'])->render());
    $pdf = tempnam(sys_get_temp_dir(), 'pdf-meta-');
    file_put_contents($pdf, $binary);
    $text = shell_exec('pdftotext '.escapeshellarg($pdf).' -');
    @unlink($pdf);

    $plain = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $text ?? '') ?? '';

    // The long id wraps at hyphens; pdftotext may split tokens or drop a
    // trailing hyphen at a line break, but every segment must still appear.
    expect($plain)->toContain('V221')
        ->and($plain)->toContain('SIMP')
        ->and($plain)->toContain('PHONE')
        ->and($plain)->toContain('20261120183431')
        ->and($plain)->toContain('due on')
        ->and($plain)->toContain('invoice date')
        ->and($plain)->not->toContain('due on invoice d'.PHP_EOL)
        ->and($plain)->toContain('Invoice No.');
})->group('pdf-visual');