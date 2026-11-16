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
});

it('renders classic invoice template when configured', function () {
    $business = BusinessSetting::singleton();
    $settings = $business->settings ?? [];
    data_set($settings, 'invoicing.invoice_print_template', 'classic');
    $business->writeSettings($settings);

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'CLASSIC-TPL',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => '2026-12-01',
        'payload' => [
            'lines' => [
                ['name' => 'Service visit', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'buyer_name' => 'Demo Buyer',
            'buyer_vat' => '300000000000003',
            'buyer_national_address' => [
                'building' => '1',
                'street' => 'Demo St',
                'district' => 'Demo Dist',
                'city' => 'Riyadh',
                'postal_code' => '12211',
            ],
            'buyer_country' => 'SA',
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']))
        ->assertOk()
        ->assertSee('print-tpl-classic', false)
        ->assertSee('Nature of goods or services', false)
        ->assertSee('Total Amount Due', false);
});