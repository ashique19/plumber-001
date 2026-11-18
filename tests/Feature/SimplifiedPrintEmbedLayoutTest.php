<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoicePrintLinks;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'legal_entity.national_address', [
        'building' => '7098',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
        'postal_code' => '12629',
    ]);
    $business->settings = $settings;
    $business->save();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

it('opens hub view on a4 modern print for simplified invoices', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-HUB-A4',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'payment_method' => 'cash',
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);

    expect(HubInvoicePrintLinks::layout($invoice))->toBe('a4')
        ->and(HubInvoicePrintLinks::urls($invoice)['view'])
        ->toBe(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4']));
});

it('renders embed modern print with aligned grid, bidi payment, address lines, and qr caption', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-EMBED-GRID',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'payment_method' => 'cash',
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
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
        ->toContain('print-tpl-modern', false)
        ->toContain('invoice-print-info-grid__col-en', false)
        ->toMatch(
            '/VAT No\.<\/td>\s*<td[^>]*invoice-print-info-grid__value[^>]*>.*?<\/td>\s*<td[^>]*invoice-print-info-grid__ar[^>]*>الرقم الضريبي/s',
        )
        ->toContain('invoice-national-address-ar__line', false)
        ->toContain('invoice-a4__qr-note-en', false)
        ->toContain('invoice-a4__qr-note-ar', false)
        ->not->toContain('data-testid="print-screen-toolbar"', false)
        ->toContain('>Payment</td>', false)
        ->toContain('invoice-print-page-label__en', false)
        ->toContain('invoice-print-page-label__ar', false)
        ->toContain('>Page 1 / 1</span>', false)
        ->toContain('>صفحة 1 / 1</span>', false)
        ->not->toContain('print-tpl-modern__sheet-footer--screen', false);

    expect(substr_count($html, 'data-print-page-label'))->toBe(1);

    $cssHref = '/build/assets/'.basename((string) glob(public_path('build/assets/invoice-print-*.css'))[0]);
    expect($html)->toContain($cssHref, false);
});

it('renders thermal cash layout with national address lines and payment bidi', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SIMP-CASH-GRID',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'payment_method' => 'cash',
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
        ],
    ]);

    $html = (string) $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash', 'embed' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('invoice-print-sheet--cash', false)
        ->toContain('invoice-national-address-ar__line', false)
        ->toContain('invoice-print-bidi-value', false);
});