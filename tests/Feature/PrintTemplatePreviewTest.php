<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

function createIssuedInvoiceForPrint(): Invoice
{
    return Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'PREVIEW-INV-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => '2026-12-01',
        'payload' => [
            'lines' => [
                ['name' => 'Service visit', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'buyer_name' => 'Demo Buyer',
        ],
    ]);
}

function createQuotationForPrint(): Quotation
{
    return Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'QUO-PREVIEW-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(30)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => StaffUser::query()->first()?->id,
    ]);
}

it('redirects invoice classic template preview to printable invoice', function () {
    createIssuedInvoiceForPrint();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.print.template-preview', ['kind' => 'invoice', 'template' => 'classic']))
        ->assertRedirect();
});

it('redirects quotation classic template preview to printable quotation', function () {
    createQuotationForPrint();

    $response = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.print.template-preview', ['kind' => 'quotation', 'template' => 'classic']));

    $response->assertRedirect();

    $target = $response->headers->get('Location') ?? '';
    expect($target)->toContain('template=classic');

    $this->get($target)->assertOk()->assertSee('print-tpl-classic', false);
});

it('renders classic invoice print with vite invoice-print-preview entry', function () {
    $invoice = createIssuedInvoiceForPrint();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            'template' => 'classic',
        ]))
        ->assertOk()
        ->assertSee('print-tpl-classic', false);
});