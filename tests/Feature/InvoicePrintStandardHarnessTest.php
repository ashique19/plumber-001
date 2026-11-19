<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
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

function standardHarnessPayload(): array
{
    return [
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
    ];
}

it('renders standard html amount words and buyer address without clipping styles', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-HARNESS-'.uniqid(),
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => standardHarnessPayload(),
    ]);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            'template' => 'modern',
            'embed' => 1,
        ]))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('ثمانية و عشرون ريال و خمسة')
        ->toContain('و سبعون هللة فقط')
        ->toContain('invoice-a4__buyer-address-en__line')
        ->toContain('Prince Abdulaziz bin Musaed bin Jalawi Street');

    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));
    expect($css)
        ->not->toContain('overflow-x: clip')
        ->toContain('overflow-x: visible');
});

it('renders dompdf html with comma-bound pdf address non-breaking tokens', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-PDF-HARNESS',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => standardHarnessPayload(),
    ]);

    $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
    $html = view($sheet['view'], $sheet['data'])->render();
    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('invoice-pdf-address-part')
        ->toContain('<span class="invoice-pdf-address-part">Al Murabba</span>')
        ->toContain('invoice-a4__buyer-address-en__line');

    $pdf = DocumentPdfRenderer::render($wrapped);
    expect(strlen($pdf))->toBeGreaterThan(2000);
});

it('renders simplified and standard embed html for viewport harness', function (string $type) {
    $payload = standardHarnessPayload();
    if ($type === 'simplified') {
        $invoice = Invoice::query()->create([
            'branch_id' => $this->branchId,
            'document_number' => 'SIMP-HARNESS-'.uniqid(),
            'document_type' => InvoiceDocumentType::Simplified,
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => [
                'payment_method' => 'cash',
                'amount_words_ar' => 'أحد عشر ريال و خمسون هللة فقط',
                'lines' => [['name' => 'Line', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
            ],
        ]);
    } else {
        $invoice = Invoice::query()->create([
            'branch_id' => $this->branchId,
            'document_number' => 'STD-HARNESS-'.uniqid(),
            'document_type' => InvoiceDocumentType::Standard,
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => $payload,
        ]);
    }

    foreach ([390, 1280] as $width) {
        $html = (string) $this->actingAs($this->owner, 'staff')
            ->get(route('admin.invoices.print', [
                'invoice' => $invoice,
                'layout' => 'a4',
                'template' => 'modern',
                'embed' => 1,
            ]))
            ->assertOk()
            ->getContent();

        expect($html)
            ->toContain('print-tpl-modern')
            ->toContain('invoice-print-words-block');
    }
})->with(['standard', 'simplified']);