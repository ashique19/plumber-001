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
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('keeps template A totals as a compact right-aligned two-column table', function () {
    $css = (string) file_get_contents(resource_path('css/print-template-modern.css'));
    expect($css)
        ->toContain('.print-tpl-modern__closing')
        ->toContain('display: flex')
        ->toContain('margin-left: auto')
        ->toContain('display: table !important')
        ->toContain('display: table-cell !important')
        ->not->toContain('grid-template-columns: minmax(160px, 200px)');

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-TOTALS-BOX-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Sample product', 'name_ar' => 'منتج تجريبي', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    expect(strpos($html, 'print-tpl-modern__closing'))->toBeLessThan((int) strpos($html, 'print-tpl-modern__words'));
    assertModernTotalsTable($html);
});

it('keeps template B and quotation totals as two-column tables', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-TOTALS-BOX-B',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $classic = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'classic']))
        ->assertOk()
        ->getContent();

    expect($classic)->toContain('print-classic__totals-wrap');
    assertTwoColumnTable($classic, 'print-classic__totals');

    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'QUO-TOTALS-BOX',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(10)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $quoHtml = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertModernTotalsTable($quoHtml);
});

it('applies the same totals table rules in PDF HTML', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-TOTALS-PDF',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('display:flex!important')
        ->toContain('display:table-cell!important')
        ->not->toContain('grid-template-columns:110px');

    assertModernTotalsTable($wrapped);
});

function assertModernTotalsTable(string $html): void
{
    expect($html)
        ->toContain('print-tpl-modern__totals-en')
        ->toContain('print-tpl-modern__totals-ar')
        ->toContain('print-tpl-modern__totals-value')
        ->toContain('print-tpl-modern__totals-total')
        ->toContain('print-tpl-modern__totals-net');

    assertTwoColumnTable($html, 'print-tpl-modern__totals');
}

function assertTwoColumnTable(string $html, string $class): void
{
    expect($html)->toMatch('/<table[^>]*class="[^"]*'.preg_quote($class, '/').'[^"]*"/');

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');
    expect($tables)->not->toBeEmpty();

    $rows = $xpath->query('.//tr', $tables->item(0));
    expect($rows->length)->toBeGreaterThanOrEqual(3);

    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        expect($cells->length)->toBe(2);
    }
}
