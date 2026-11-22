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
    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'legal_entity.address', 'Al Olaya Street, Building 12, Riyadh 12211, Kingdom of Saudi Arabia');
    data_set($settings, 'legal_entity.national_address', [
        'building' => '7098',
        'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
        'district' => 'Al Malaz',
        'city' => 'Riyadh',
        'postal_code' => '12629',
        'additional' => '3652',
        'country' => 'SA',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
    ]);
    $business->settings = $settings;
    $business->save();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('renders an 80mm cash receipt from the national address without line-item tables or bangla', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-CASH80-001',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('phase1-demo'),
        'payload' => [
            'payment_method' => 'cash',
            'buyer_name' => 'Shah Jamal',
            'buyer_phone' => '05012340244',
            'lines' => [
                ['name' => 'Ladies Hijab Cap Ni-11', 'name_ar' => 'قبعة حجاب نسائية', 'quantity' => 1, 'unit_price_ex_vat' => 5, 'vat_rate' => 15],
                ['name' => 'Ladies Hijab Cap Ni-12', 'quantity' => 1, 'unit_price_ex_vat' => 6, 'vat_rate' => 15],
            ],
        ],
    ]);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash', 'locale' => 'bn']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('invoice-print-sheet--cash')
        ->toContain('invoice-cash80')
        ->toContain('@page { size: 80mm auto; margin: 0; }')
        ->toContain('SIMPLIFIED TAX INVOICE')
        ->toContain('فاتورة ضريبية مبسطة')
        ->toContain('7098, Ahmad Ibn Al Hussain Ibn Shuqair St')
        ->toContain('أحمد بن الحسين بن شقير')
        ->not->toContain('Al Olaya Street')
        ->toContain('Shah Jamal')
        ->toContain('1 x 5.75')
        ->toContain('قبعة حجاب نسائية')
        ->toContain('Tax inclusive:')
        ->toContain('الضريبة شاملة')
        ->toContain('Before VAT')
        ->toContain('Thank you for shopping')
        ->toContain('شكراً لتسوقكم')
        ->toContain('id="receipt-barcode"')
        ->toContain('data-value="SIMP-CASH80-001"')
        ->toContain('<span class="invoice-sar-symbol">SAR</span>')
        ->toContain('invoice-print-toolbar--screen')
        ->toContain('no-print')
        ->not->toContain('invoice-a4__table--product-lines')
        ->not->toContain('invoice-print-info-grid')
        ->not->toContain('পৃষ্ঠা');
});
