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

const SAMPLE_AMOUNT_WORDS_AR = 'ثمانية و عشرون ريال و خمسة و سبعون هللة فقط';

it('splits standard 28.75 SAR amount words into two html lines without trailing و', function () {
    $wrapped = InvoicePrintArabicPrelines::amountWordsBlock(SAMPLE_AMOUNT_WORDS_AR);

    expect($wrapped['lines'])->toHaveCount(2)
        ->and(implode(' ', $wrapped['lines']))->toBe(SAMPLE_AMOUNT_WORDS_AR);

    foreach ($wrapped['lines'] as $line) {
        expect($line)->not->toMatch('/\sو$/u')->and($line)->not->toBe('و');
        expect(count(preg_split('/\s+/u', trim($line)) ?: []))->toBeGreaterThan(0);
    }

    $lastWords = preg_split('/\s+/u', (string) end($wrapped['lines'])) ?: [];
    expect(count($lastWords))->toBeGreaterThan(1);
});

it('renders standard modern html with two nowrap arabic amount word prelines for 28.75 SAR', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-2875-WORDS',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'amount_words_en' => 'Twenty-Eight Saudi Riyals and Seventy-Five Halalas only',
            'amount_words_ar' => SAMPLE_AMOUNT_WORDS_AR,
            'lines' => [
                ['name' => 'Line', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
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

    expect($html)
        ->toContain('invoice-print-words-block__en')
        ->toContain('invoice-print-words-block__ar')
        ->toContain('twenty-eight Saudi Riyals and seventy-five Halalas only')
        ->toContain('invoice-pdf-ar-preline" dir="rtl"');

    $wordsStart = strpos($html, 'invoice-print-words-block print-tpl-modern__words');
    expect($wordsStart)->toBeInt();
    $segment = substr($html, $wordsStart, 4000) ?: '';

    preg_match_all('/<div class="invoice-pdf-ar-preline"/', $segment, $matches);
    expect($matches[0])->toHaveCount(2);

    expect($segment)->toContain('ثمانية و عشرون ريال و خمسة و سبعون');
    expect($segment)->toContain('هللة فقط');
    expect($segment)->not->toContain(SAMPLE_AMOUNT_WORDS_AR);
});