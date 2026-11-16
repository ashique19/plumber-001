<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Invoicing\InvoicePrintPresenter;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders a4 with bilingual labels buyer vat cr and phase1 qr', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-PRINT-TPL',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => '2026-12-01',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe install', 'name_ar' => 'تركيب مواسير', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'buyer_name' => 'Buyer',
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1010000000',
        ],
    ]);

    $presented = InvoicePrintPresenter::fromInvoice(BusinessSetting::singleton(), $invoice);
    expect($presented['customer']['vat_number'] ?? null)->toBe('300000000000003')
        ->and($presented['customer']['cr_number'] ?? null)->toBe('1010000000')
        ->and($presented['qr_tlv_base64'])->toBeNull()
        ->and($presented['qr_phase1_fallback'])->toBeFalse()
        ->and($presented['print_watermark'])->toBeTrue();

    app()->setLocale('bn');

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))
        ->assertOk()
        ->assertSee(__('pos.receipt_title_standard_en'), false)
        ->assertSee(__('pos.receipt_title_standard_ar'), false)
        ->assertSee('VAT No.', false)
        ->assertSee('الرقم الضريبي', false)
        ->assertSee('1010000000', false)
        ->assertSee(__('invoices.print_not_cleared'), false)
        ->assertDontSee('receipt-qr', false)
        ->assertSee('sar-riyal-symbol.svg', false)
        ->assertDontSee('একীভূত', false);
});

it('renders cash simplified title and bilingual table headers', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'SIMP-PRINT-TPL',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Visit', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15],
            ],
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->assertOk()
        ->assertSee(__('pos.receipt_title_simplified_en'), false)
        ->assertSee(__('pos.receipt_title_simplified_ar'), false)
        ->assertSee('Unit price', false)
        ->assertSee('سعر الوحدة', false)
        ->assertDontSee('cash_net_amount', false);
});