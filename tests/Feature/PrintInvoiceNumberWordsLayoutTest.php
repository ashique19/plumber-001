<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('wraps long invoice numbers in print CSS without horizontal overflow scrollbars', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toContain('.invoice-a4__buyer-table-value--nowrap')
        ->toContain('overflow-wrap: anywhere')
        ->toContain('word-break: break-all')
        ->not->toContain('overflow-x: auto');
});

it('aligns amount-in-words with modern totals and uses a shared baseline row', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'))
        .(string) file_get_contents(resource_path('css/print-template-modern.css'));

    expect($css)
        ->toContain('align-items: baseline')
        ->toContain('.print-tpl-modern__words')
        ->toContain('max-width: 26rem')
        ->toContain('margin-left: auto');
});

it('renders modern print HTML with amount words block beside totals width', function () {
    $longNumber = 'STD-20261117-201530';
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => $longNumber,
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
            'amount_words_en' => 'eleven Saudi Riyals and fifty Halalas only',
            'amount_words_ar' => 'أحد عشر ريال و خمسون هللة فقط',
        ],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern', 'locale' => 'en']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('invoice-a4__buyer-table-value--nowrap')
        ->toContain($longNumber)
        ->toContain('invoice-print-words-block print-tpl-modern__words')
        ->toContain('invoice-print-words-block__en')
        ->toContain('invoice-print-words-block__ar')
        ->toContain('eleven Saudi Riyals and fifty Halalas only')
        ->toContain('أحد عشر ريال و خمسون هللة فقط');
});

it('includes dompdf overlay rules for wrapped invoice numbers and baseline amount words', function () {
    $wrapped = DocumentPdfRenderer::wrapHtml(
        '<div class="invoice-print-root--pdf">'
        .'<span class="invoice-a4__buyer-table-value--nowrap">STD-20261117-201530</span>'
        .'<div class="print-tpl-modern__words invoice-print-words-block">'
        .'<p class="invoice-print-words-block__en">words</p>'
        .'</div></div>',
    );

    expect($wrapped)
        ->toContain('invoice-a4__buyer-table-value--nowrap{white-space:normal!important')
        ->toContain('word-break:break-all!important')
        ->toContain('print-tpl-modern__words')
        ->toContain('align-items:baseline!important');
});