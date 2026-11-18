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

it('renders modern A4 totals as three columns per row without stacked Arabic', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-TOTALS-3COL',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern', 'locale' => 'bn']))
        ->assertOk()
        ->getContent();

    assertInvoicePrintTotalsThreeColumns($html, 'print-tpl-modern__totals');
    expect($html)
        ->toContain('invoice-print-totals__ar')
        ->toContain('المجموع الفرعي')
        ->toContain('data-page-en="Page :current / :total"')
        ->toContain('invoice-print-page-label__en', false)
        ->toContain('>Page 1 / 1</span>', false)
        ->toContain('>صفحة 1 / 1</span>', false)
        ->not->toContain('পৃষ্ঠা');
});

it('uses the same three-column totals on thermal cash, quotation print, and PDF HTML', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-TOTALS-3COL',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Part', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
    ]);

    $cash = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash', 'locale' => 'ar']))
        ->assertOk()
        ->getContent();

    assertInvoicePrintTotalsThreeColumns($cash, 'invoice-cash__totals');

    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'QUO-TOTALS-3COL',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $quo = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'modern', 'locale' => 'en']))
        ->assertOk()
        ->getContent();

    assertInvoicePrintTotalsThreeColumns($quo, 'print-tpl-modern__totals');

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($wrapped)
        ->toContain('invoice-print-totals__ar')
        ->toContain('table-layout:fixed!important')
        ->toContain('invoice-print-totals__value{white-space:nowrap!important');

    assertInvoicePrintTotalsThreeColumns($wrapped, 'print-tpl-modern__totals');
});

function assertInvoicePrintTotalsThreeColumns(string $html, string $tableClass): void
{
    expect($html)->toMatch('/<table[^>]*class="[^"]*'.preg_quote($tableClass, '/').'[^"]*"/');

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " '.$tableClass.' ")]');
    expect($tables)->not->toBeEmpty();

    $rows = $xpath->query('.//tr', $tables->item(0));
    expect($rows->length)->toBeGreaterThanOrEqual(3);

    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        expect($cells->length)->toBe(3);

        expect($cells->item(0)->getAttribute('class'))->toContain('invoice-print-totals__en');
        expect($cells->item(1)->getAttribute('class'))->toContain('invoice-print-totals__ar');
        expect($cells->item(2)->getAttribute('class'))->toContain('invoice-print-totals__value');
        expect($cells->item(2)->getAttribute('dir'))->toBe('ltr');
    }
}