<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('renders pre-broken english amount words with nowrap prelines in modern html', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-WORDS-EN',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'payment_method' => 'cash',
            'amount_words_en' => 'Eleven Saudi Riyals and Fifty Halalas only',
            'amount_words_ar' => 'أحد عشر ريال و خمسون هللة فقط',
            'lines' => [
                ['name' => 'Test Modal Product', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
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
        ->toContain('invoice-print-en-preline')
        ->toContain('eleven Saudi Riyals and')
        ->toContain('fifty Halalas only')
        ->not->toContain('>eleven Saudi Riyals and fifty Halalas only<')
        ->toContain('invoice-print-page-label__print')
        ->toContain('SIMP-WORDS-EN');
});