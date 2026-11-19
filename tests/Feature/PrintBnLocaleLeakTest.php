<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
    app()->setLocale('bn');
});

it('keeps quotation A4 print English and Arabic when UI locale is Bangla', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->branchId,
        'quotation_number' => 'QUO-20261117-120718',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(30)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', ['quotation' => $quotation, 'template' => 'modern', 'locale' => 'bn']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Quotation')
        ->toContain('عرض سعر')
        ->toContain('invoice-print-totals__ar')
        ->toContain('invoice-print-info-grid__value--nowrap')
        ->toContain('QUO-20261117-120718')
        ->toContain('invoice-print-page-label__screen', false)
        ->toContain('invoice-print-page-label__print', false)
        ->not->toContain('কোটেশন')
        ->not->toContain('পৃষ্ঠা')
        ->not->toContain('invoice-a4__title-bn');
});

it('keeps invoice A4, cash, and PDF output free of Bangla UI strings', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'STD-20261117-170322',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [
                ['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $a4 = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern', 'locale' => 'bn']))
        ->assertOk()
        ->getContent();

    expect($a4)
        ->toContain('invoice-print-totals__value')
        ->not->toContain('পৃষ্ঠা');

    $cash = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash', 'locale' => 'bn']))
        ->assertOk()
        ->getContent();

    expect($cash)->toContain('invoice-print-totals__ar');

    $sheet = PrintSheetFactory::quotation(
        Quotation::query()->create([
            'branch_id' => $this->branchId,
            'quotation_number' => 'QUO-PDF-BN',
            'invoice_mode' => 'simplified',
            'validity_until' => now()->addDays(7)->toDateString(),
            'payload' => ['lines' => [['name' => 'X', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]]],
            'created_by_staff_user_id' => $this->owner->id,
        ]),
        Request::create('/', 'GET', ['locale' => 'bn', 'template' => 'modern']),
        true,
    );

    $pdfHtml = DocumentPdfRenderer::wrapHtml(view($sheet['view'], $sheet['data'])->render());

    expect($pdfHtml)
        ->not->toContain('কোটেশন')
        ->toContain('invoice-print-totals__ar');
});