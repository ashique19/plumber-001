<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Livewire\Quotations\QuotationHub;
use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Support\Invoicing\IssueInvoicePrefill;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    Auth::guard('staff')->login($this->owner);
});

afterEach(function () {
    Auth::guard('staff')->logout();
    IssueInvoicePrefill::clear();
});

it('renders quotation totals preview breakdown like invoices', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->html();

    expect($html)->toContain(__('invoices.subtotal_ex_vat'))
        ->and($html)->toContain(__('invoices.total_vat'))
        ->and($html)->toContain('document-primary-submit');
});

it('creates a quotation from the livewire form', function () {
    $product = Product::query()->where('is_active', true)->firstOrFail();

    $countBefore = Quotation::query()->count();

    Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->set('invoiceMode', 'simplified')
        ->set('validityUntil', now('Asia/Riyadh')->addDays(14)->format('Y-m-d'))
        ->set('lines', [[
            'name' => 'Pipe fitting',
            'name_ar' => '',
            'quantity' => '2',
            'unit_price' => (string) $product->price_ex_vat,
            'vat_rate' => (string) $product->vat_rate,
            'product_id' => $product->id,
            'unit_label' => '',
        ]])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.quotations'));

    expect(Quotation::query()->count())->toBe($countBefore + 1);
});

it('prints bilingual quotation title labels without zatca qr markers', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-TEST-001',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                [
                    'name' => 'Valve',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 100,
                    'vat_rate' => 15,
                ],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $response = $this->get(route('admin.quotations.print', $quotation));

    $response->assertOk()
        ->assertSee('Quotation', false)
        ->assertSee('عرض سعر', false)
        ->assertSee('কোটেশন', false)
        ->assertSee('Valid until', false)
        ->assertSee('صالح حتى', false)
        ->assertDontSee('SIMPLIFIED TAX INVOICE', false)
        ->assertDontSee('STANDARD TAX INVOICE', false)
        ->assertDontSee('ZATCA', false)
        ->assertDontSee('qr_tlv', false)
        ->assertDontSee('receipt-qr', false);
});

it('prefills issue invoice from quotation convert action using default mode', function () {
    $defaultMode = $this->business->defaultInvoiceDocumentType() === InvoiceDocumentType::Standard
        ? 'standard'
        : 'simplified';

    $quotation = Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-TEST-002',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                [
                    'name' => 'Gasket',
                    'quantity' => 3,
                    'unit_price_ex_vat' => 50,
                    'vat_rate' => 15,
                ],
            ],
            'buyer_name' => 'Acme Plumbing',
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->call('convertToInvoice', $quotation->id)
        ->assertRedirect(route('admin.invoices.issue', [
            'mode' => $defaultMode,
            'from_quotation' => $quotation->id,
        ], false));

    $component = Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['from_quotation' => $quotation->id])
        ->test(IssueInvoice::class, ['mode' => $defaultMode]);

    expect($component->get('lines')[0]['name'])->toBe('Gasket');
    expect((float) $component->get('lines')[0]['quantity'])->toBe(3.0);
    expect($component->get('buyerName'))->toBe('Acme Plumbing');
});

it('serves quotation hub routes for authorized staff smoke', function () {
    $this->get(route('admin.quotations'))->assertOk();
    $this->get(route('admin.quotations.create'))->assertOk();

    Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->assertSee(__('quotations.create'), false);
});