<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Invoicing\StandardInvoiceBuyerSnapshotRefresh;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DemoStaffSeeder)->run();
});

it('fills missing postal code on invoice payload from linked customer', function () {
    $customer = Customer::query()->create([
        'name' => 'Refresh Co',
        'phone' => '0502222111',
        'profile' => [
            'national_address' => [
                'building' => '10',
                'street' => 'Main St',
                'district' => 'Center',
                'city' => 'Riyadh',
                'postal_code' => '11564',
                'country' => 'SA',
            ],
        ],
    ]);

    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-REFRESH-UNIT',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'invoice_category' => 'standard',
            'buyer_customer_id' => $customer->id,
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1012345678',
            'buyer_national_address' => [
                'building' => '10',
                'street' => 'Main St',
                'district' => 'Center',
                'city' => 'Riyadh',
            ],
        ],
    ]);

    $merged = StandardInvoiceBuyerSnapshotRefresh::mergeMissingFromCustomer($invoice);

    expect(data_get($merged, 'buyer_national_address.postal_code'))->toBe('11564')
        ->and(data_get($merged, 'buyer_national_address.country'))->toBe('SA');
});