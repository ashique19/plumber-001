<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Quotations\QuotationHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('enlarges the centered print logo and right-aligns Arabic print text', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'))
        .(string) file_get_contents(resource_path('css/print-template-classic.css'));

    expect($css)
        ->toContain('max-height: 96px')
        ->toContain("text-align: right")
        ->toContain('invoice-sar-symbol__svg');
});

it('renders an inline Saudi riyal SVG on invoice print', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-SAR-001',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'locale' => 'en']))
        ->assertOk()
        ->assertSee('invoice-sar-symbol__svg', false)
        ->assertSee('viewBox="0 0 1124.14 1256.39"', false)
        ->assertDontSee('sar-riyal-symbol.svg', false);
});

it('titles quotations without tax-invoice wording, type badges, or QR', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'QUO-POLISH-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(14)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 80, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'classic', 'locale' => 'en']))
        ->assertOk()
        ->assertSee(trans('quotations.print_title', [], 'en'), false)
        ->assertSee(trans('quotations.print_title', [], 'ar'), false)
        ->assertDontSee('SIMPLIFIED TAX INVOICE', false)
        ->assertDontSee('receipt-qr', false);

    $hub = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->html();

    expect($hub)
        ->not->toContain(__('quotations.col_type'))
        ->not->toContain(__('quotations.invoice_mode_simplified'));
});
