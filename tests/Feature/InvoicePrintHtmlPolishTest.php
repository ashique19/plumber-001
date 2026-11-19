<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\InvoicePrintArabicPrelines;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('styles qr captions at least qr width and keeps pre-broken en lines', function () {
    $modernCss = (string) file_get_contents(resource_path('css/print-template-modern.css'));

    expect($modernCss)
        ->toContain('min-width: 200px')
        ->toContain('.print-tpl-modern__qr-wrap .invoice-a4__qr-note-lines')
        ->toContain('white-space: nowrap');
});

it('pre-breaks arabic amount words for html print without trailing و on a line', function () {
    $text = 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط';
    $lines = InvoicePrintArabicPrelines::amountWordsBlock($text)['lines'];

    expect(count($lines))->toBeLessThanOrEqual(2);

    foreach ($lines as $line) {
        expect($line)->not->toMatch('/\sو$/u')
            ->and($line)->not->toBe('و');
    }
});

function assertModernPrintHtml(string $html, bool $expectQr = true): void
{
    expect($html)->toContain('invoice-pdf-ar-preline');

    if ($expectQr) {
        expect($html)
            ->toContain('invoice-a4__qr-note-lines')
            ->toContain('This QR code is encoded as per')
            ->toContain('ZATCA e-invoicing requirements');
    }
}

it('renders simplified modern html print with qr captions, pre-broken amount words, and buyer address lines', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-HTML-POLISH',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'payment_method' => 'cash',
            'buyer_name_en' => 'Walk-in',
            'amount_words_en' => 'Twenty-Eight Saudi Riyals and Seventy-Five Halalas only',
            'amount_words_ar' => 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط',
            'lines' => [
                ['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            'template' => 'modern',
        ]))
        ->assertOk()
        ->getContent();

    assertModernPrintHtml($html);
    expect($html)->toContain('ثمانية و عشرون ريال');
});

it('renders standard modern html print with structured buyer address lines for mobile and desktop embed', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-HTML-POLISH',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
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
            'amount_words_en' => 'Eleven Saudi Riyals and Fifty Halalas only',
            'amount_words_ar' => 'أحد عشر ريال و خمسون هللة فقط',
            'lines' => [
                ['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
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

    assertModernPrintHtml($html, expectQr: false);

    expect($html)
        ->toContain('7780, Prince Abdulaziz bin Musaed bin Jalawi Street')
        ->toContain('Al Murabba, Riyadh 12612, 3841')
        ->toContain('invoice-a4__buyer-address-en__line')
        ->toContain('invoice-print-sheet');
});