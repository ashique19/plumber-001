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

function moneyHeaderInvoicePayload(): array
{
    return [
        'lines' => [
            ['name' => 'Visit', 'name_ar' => 'زيارة', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
        ],
    ];
}

function createMoneyHeaderInvoice(int $branchId, InvoiceDocumentType $type, string $number): Invoice
{
    return Invoice::query()->create([
        'branch_id' => $branchId,
        'document_number' => $number,
        'document_type' => $type,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => moneyHeaderInvoicePayload(),
    ]);
}

function assertModernLineMoneyHeaders(string $html, bool $expectLiteralArabic = true): void
{
    expect($html)
        ->toContain('Price (SAR)')
        ->toContain('Taxable amount (SAR)')
        ->toContain('VAT amount (SAR)')
        ->toContain('Line amount (SAR)')
        ->not->toContain('VAT amount (+%)');

    if ($expectLiteralArabic) {
        expect($html)
            ->toContain('السعر')
            ->toContain('المبلغ الخاضع للضريبة')
            ->toContain('القيمة المضافة')
            ->toContain('المجموع');
    }
}

function extractHtmlSection(string $html, string $startNeedle, string $endNeedle): string
{
    $start = strpos($html, $startNeedle);
    expect($start)->not->toBeFalse();
    $end = strpos($html, $endNeedle, $start);
    expect($end)->not->toBeFalse();

    return substr($html, $start, $end - $start);
}

function assertLineItemsHaveBareAmounts(string $html, string $tableClass, bool $includeVatAmount = true): void
{
    $section = extractHtmlSection($html, $tableClass, '</table>');

    expect($section)
        ->toContain('100.00')
        ->toContain('115.00')
        ->toContain('15%')
        ->not->toContain('invoice-sar-symbol')
        ->not->toContain('>SAR</span>');

    if ($includeVatAmount) {
        expect($section)->toContain('15.00');
    }
}

function assertTotalsPlainExceptGrand(string $html, string $tableClass): void
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $tables = $xpath->query('//table[contains(concat(" ", normalize-space(@class), " "), " '.$tableClass.' ")]');
    expect($tables)->not->toBeEmpty();

    $rows = $xpath->query('.//tr', $tables->item(0));
    $seen = [];
    foreach ($rows as $row) {
        $en = trim($xpath->query('.//*[contains(@class, "invoice-print-totals__en")]', $row)->item(0)?->textContent ?? '');
        $valueCell = $xpath->query('.//*[contains(@class, "invoice-print-totals__value")]', $row)->item(0);
        expect($valueCell)->not->toBeNull();
        $valueHtml = $dom->saveHTML($valueCell);
        $valueText = trim(preg_replace('/\s+/', ' ', $valueCell->textContent) ?? '');
        $seen[$en] = ['html' => $valueHtml, 'text' => $valueText];
    }

    expect($seen)->toHaveKey('Subtotal')
        ->and($seen['Subtotal']['text'])->toBe('100.00')
        ->and($seen['Subtotal']['html'])->not->toContain('invoice-sar-symbol')
        ->and($seen)->toHaveKey('Total VAT')
        ->and($seen['Total VAT']['text'])->toBe('15.00')
        ->and($seen['Total VAT']['html'])->not->toContain('invoice-sar-symbol')
        ->and($seen)->toHaveKey('Total')
        ->and($seen['Total']['text'])->toContain('115.00')
        ->and($seen['Total']['html'])->toContain('invoice-sar-symbol')
        ->and($seen['Total']['text'])->toContain('SAR');
}

it('adds SAR to modern A4 money headers and keeps it only on the grand total', function (InvoiceDocumentType $type) {
    $invoice = createMoneyHeaderInvoice($this->branchId, $type, 'MONEY-A4-'.$type->value);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertModernLineMoneyHeaders($html);
    assertLineItemsHaveBareAmounts($html, 'invoice-a4__table--product-lines print-tpl-modern__lines');
    assertTotalsPlainExceptGrand($html, 'print-tpl-modern__totals');
})->with([
    InvoiceDocumentType::Standard,
    InvoiceDocumentType::Simplified,
    InvoiceDocumentType::CreditNote,
    InvoiceDocumentType::DebitNote,
]);

it('uses the same money header and amount rules in PDF html for every document type', function (InvoiceDocumentType $type) {
    $invoice = createMoneyHeaderInvoice($this->branchId, $type, 'MONEY-PDF-'.$type->value);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $wrapped = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    assertModernLineMoneyHeaders($wrapped, expectLiteralArabic: false);
    assertLineItemsHaveBareAmounts($wrapped, 'invoice-a4__table--product-lines print-tpl-modern__lines');
    assertTotalsPlainExceptGrand($wrapped, 'print-tpl-modern__totals');
    expect($wrapped)->toContain('white-space:nowrap!important');
})->with([
    InvoiceDocumentType::Standard,
    InvoiceDocumentType::Simplified,
    InvoiceDocumentType::CreditNote,
    InvoiceDocumentType::DebitNote,
]);

it('keeps SAR only on the cash receipt grand total and uses bare line amounts', function () {
    $invoice = createMoneyHeaderInvoice($this->branchId, InvoiceDocumentType::Simplified, 'MONEY-CASH');

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('invoice-cash80__item')
        ->toContain('15%')
        ->toContain('115.00')
        ->toContain('invoice-cash80__grand')
        ->toContain('<span class="invoice-sar-symbol">SAR</span>');

    $items = extractHtmlSection($html, 'invoice-cash80__lines', 'invoice-cash80__totals');
    expect($items)
        ->toContain('1 x 115.00')
        ->toContain('115.00')
        ->toContain('15%')
        ->not->toContain('invoice-sar-symbol')
        ->not->toContain('>SAR</span>');

    $grand = extractHtmlSection($html, 'invoice-cash80__grand', 'invoice-cash80__vat');
    expect($grand)
        ->toContain('invoice-sar-symbol')
        ->toContain('115.00');
});

it('keeps quotation modern print money headers and grand-total SAR consistent', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'MONEY-QUO',
        'invoice_mode' => 'standard',
        'validity_until' => now()->addDays(10)->toDateString(),
        'payload' => moneyHeaderInvoicePayload(),
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    assertModernLineMoneyHeaders($html);
    assertLineItemsHaveBareAmounts($html, 'invoice-a4__table--product-lines print-tpl-modern__lines');
    assertTotalsPlainExceptGrand($html, 'print-tpl-modern__totals');
});

it('does not wrap modern money column english headers in print css', function () {
    $css = (string) file_get_contents(resource_path('css/print-template-modern.css'));
    expect($css)->toContain('thead th.col-num .invoice-print-th-label__en')
        ->toContain('white-space: nowrap');
});
