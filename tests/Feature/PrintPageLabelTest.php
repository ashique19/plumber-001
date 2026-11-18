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
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    app()->setLocale('en');
});

it('hides the HTML footer page label in browser print because CSS page counters are unreliable', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toContain('@media print')
        ->toContain('.invoice-print-page-label')
        ->toContain('display: none !important')
        ->not->toContain('counter(page)');
});

it('prints template A and B footers with a screen-only document label', function (string $template) {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'SIMP-PAGE-001',
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
        ->get(route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => 'a4',
            'template' => $template,
            'locale' => 'en',
        ]))
        ->assertOk()
        ->assertSee('invoice-print-page-label__screen', false)
        ->assertSee('SIMP-PAGE-001', false)
        ->assertDontSee('invoice-print-page-label__print', false)
        ->assertDontSee('صفحة', false)
        ->assertDontSee('Page 0 of 0', false)
        ->assertDontSee('Page 0 / 0', false);
})->with(['modern', 'classic']);

it('prints quotation templates without Page 0 of 0', function (string $template) {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'QUO-PAGE-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(30)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => StaffUser::query()->first()?->id,
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', [
            'quotation' => $quotation,
            'template' => $template,
            'locale' => 'en',
        ]))
        ->assertOk()
        ->assertSee('invoice-print-page-label__screen', false)
        ->assertSee('QUO-PAGE-001', false)
        ->assertDontSee('Page 0 of 0', false);
})->with(['modern', 'classic']);
