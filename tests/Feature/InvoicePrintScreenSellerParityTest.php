<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
});

/**
 * Collapse inconsequential whitespace so screen HTML compares like-for-like with the 08232c63 baseline.
 */
function normalizeModernSellerHeaderSection(string $html): string
{
    $html = preg_replace('/\r\n/', "\n", $html) ?? $html;
    $html = preg_replace('/[ \t]+/', ' ', $html) ?? $html;
    $html = preg_replace('/>\s+</', '><', $html) ?? $html;

    return trim($html);
}

function extractModernSellerHeaderSection(string $html): string
{
    expect($html)->toMatch('/<section class="invoice-a4__header print-tpl-modern__seller">.*?<\/section>/s');

    if (! preg_match('/<section class="invoice-a4__header print-tpl-modern__seller">.*?<\/section>/s', $html, $matches)) {
        throw new RuntimeException('Seller header section not found in print HTML.');
    }

    return normalizeModernSellerHeaderSection($matches[0]);
}

it('keeps modern A4 print seller header html identical to the 08232c63 screen baseline', function () {
    $this->withoutVite();

    $business = BusinessSetting::singleton();
    $settings = $business->settings;
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

    $invoice = Invoice::query()->create([
        'branch_id' => $this->branchId,
        'document_number' => 'SCREEN-SELLER-08232c63',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => ['lines' => [['name' => 'Item', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]]],
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern']))
        ->assertOk()
        ->getContent();

    $seller = extractModernSellerHeaderSection($html);

    expect($seller)
        ->not->toContain('invoice-pdf-seller-en-compact', false)
        ->not->toContain('invoice-pdf-seller-line', false);

    $fixturePath = base_path('tests/Fixtures/print/modern-seller-header-screen-08232c63.html');
    expect($fixturePath)->toBeReadableFile();

    $fixture = normalizeModernSellerHeaderSection((string) file_get_contents($fixturePath));

    expect($seller)->toBe($fixture);
});