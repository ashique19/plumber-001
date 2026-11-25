<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PosPaymentMethod;
use App\Livewire\Admin\BusinessSettings;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosOrderService;
use App\Support\Pos\ReceiptThermalPresenter;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\ZHeartFakeSeller;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->branchId = Branch::query()->first()?->id;
    $this->actingAs($this->owner, 'staff');
});

function zhIssuedInvoice(array $attributes = []): Invoice
{
    return Invoice::query()->create(array_merge([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'SIMP-ZH-'.uniqid(),
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'qr_tlv_base64' => base64_encode('qr-demo'),
        'payload' => [
            'payment_method' => 'cash',
            'lines' => [['name' => 'Pipe repair', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15]],
        ],
    ], $attributes));
}

it('sends no seller to Z-Heart and stores the returned seller block on POS invoices', function () {
    ZHeartFakeSeller::$block = ZHeartFakeSeller::sample();
    ['business' => $business, 'branch' => $branch, 'cashier' => $cashier, 'product' => $product] = posBusinessSetup();
    BusinessContext::set($business);

    $orders = app(PosOrderService::class);
    $order = $orders->openOrder($branch, $cashier);
    $orders->addProduct($order, $product, 1);
    $paid = app(PosCheckoutService::class)->pay($order->fresh(), PosPaymentMethod::Cash, $cashier);
    $paid->load(['branch', 'cashier']);
    $invoice = $paid->invoice()->firstOrFail();

    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/api/v1/invoices/sign')) {
            return false;
        }
        $invoice = $request->data()['invoice'] ?? [];

        return $invoice !== [] && ! array_key_exists('seller', $invoice);
    });

    expect($invoice->zheart_seller)->toBe(ZHeartFakeSeller::sample());

    $thermal = ReceiptThermalPresenter::compose($business->fresh(), $paid, $invoice, $branch);
    expect($thermal['seller_en'])->toBe('ZH Plumbing Placeholder LLC')
        ->and($thermal['seller_ar'])->toBe('شركة زد هارت للسباكة التجريبية')
        ->and($thermal['trn'])->toBe('399999999900003');

    $this->actingAs($cashier, 'staff')
        ->get(route('admin.pos.receipt', [$paid, 'locale' => 'en', 'layout' => 'receipt']))
        ->assertOk()
        ->assertSee('ZH Plumbing Placeholder LLC', false)
        ->assertSee('399999999900003', false);
});

it('renders the stored Z-Heart seller and QR on the A4 invoice print', function () {
    $localVat = (string) data_get($this->business->settings, 'legal_entity.vat_number');
    $invoice = zhIssuedInvoice(['zheart_seller' => ZHeartFakeSeller::sample()]);

    $html = (string) $this->get(route('admin.invoices.print', [
        'invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern', 'locale' => 'en',
    ]))->assertOk()->getContent();

    expect($html)
        ->toContain('ZH Plumbing Placeholder LLC')
        ->toContain('شركة زد هارت للسباكة التجريبية')
        ->toContain('399999999900003')
        ->toContain('1010010000')
        ->toContain('King Fahd Rd')
        ->toContain('invoice-a4__qr-note-en');

    // Z-Heart sends no Arabic address parts: the English address must not be repeated in the Arabic slot.
    expect(substr_count($html, 'King Fahd Rd'))->toBe(1);

    if ($localVat !== '' && $localVat !== '399999999900003') {
        expect($html)->not->toContain($localVat);
    }
});

it('falls back to local legal settings only for old invoices without a stored seller', function () {
    $settings = $this->business->settings;
    data_set($settings, 'legal_entity.name_en', 'Local Legacy Plumbing');
    data_set($settings, 'legal_entity.vat_number', '311111111100003');
    $this->business->forceFill(['settings' => $settings])->save();

    $invoice = zhIssuedInvoice();

    $html = (string) $this->get(route('admin.invoices.print', [
        'invoice' => $invoice, 'layout' => 'a4', 'template' => 'modern', 'locale' => 'en',
    ]))->assertOk()->getContent();

    expect($html)->toContain('Local Legacy Plumbing')
        ->toContain('311111111100003')
        ->not->toContain('ZH Plumbing Placeholder LLC');
});

it('prints quotations with the latest Z-Heart seller and no QR', function () {
    zhIssuedInvoice(['zheart_seller' => ZHeartFakeSeller::sample()]);
    $quotation = Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-ZH-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => ['lines' => [['name' => 'Gasket', 'quantity' => 1, 'unit_price_ex_vat' => 50, 'vat_rate' => 15]]],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $html = (string) $this->get(route('admin.quotations.print', ['quotation' => $quotation, 'locale' => 'en']))
        ->assertOk()->getContent();

    expect($html)->toContain('ZH Plumbing Placeholder LLC')
        ->toContain('399999999900003')
        ->not->toContain('receipt-qr')
        ->not->toContain('qr_tlv');
});

it('shows legal identity read-only as managed by Z-Heart and ignores it on save', function () {
    $before = (array) data_get($this->business->fresh()->settings, 'legal_entity');

    $component = Livewire::test(BusinessSettings::class)->set('section', 'legal');
    $html = $component->html();

    expect($html)
        ->toContain('Z-Heart')
        ->toContain('data-legal-readonly')
        ->not->toContain('name="legalNameEn"')
        ->not->toContain('name="legalNameAr"')
        ->not->toContain('name="vatNumber"')
        ->not->toContain('name="crNumber"')
        ->not->toContain('name="unifiedNumber"')
        ->not->toContain('name="naBuilding"')
        ->not->toContain('name="naPostal"')
        ->toContain('name="legalEmail"')
        ->toContain('name="naStreetAr"');

    $component
        ->set('legalNameEn', 'Changed Name')
        ->set('vatNumber', '300000000000003')
        ->set('crNumber', '999')
        ->set('naPostal', '00000')
        ->set('legalEmail', 'office@example.test')
        ->call('saveLegal')
        ->assertHasNoErrors();

    $after = (array) data_get(BusinessSetting::singleton()->fresh()->settings, 'legal_entity');
    expect($after['name_en'] ?? null)->toBe($before['name_en'] ?? null)
        ->and($after['vat_number'] ?? null)->toBe($before['vat_number'] ?? null)
        ->and($after['cr_number'] ?? null)->toBe($before['cr_number'] ?? null)
        ->and(data_get($after, 'national_address.postal_code'))->toBe(data_get($before, 'national_address.postal_code'))
        ->and($after['email'])->toBe('office@example.test');
});

it('shows the seller from the latest Z-Heart invoice on the legal tab', function () {
    zhIssuedInvoice(['zheart_seller' => ZHeartFakeSeller::sample()]);

    Livewire::test(BusinessSettings::class)
        ->set('section', 'legal')
        ->assertSee('ZH Plumbing Placeholder LLC')
        ->assertSee('399999999900003');
});
