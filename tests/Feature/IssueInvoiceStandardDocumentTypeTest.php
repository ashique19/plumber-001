<?php

use App\Enums\InvoiceDocumentType;
use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StaffUser;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

function completeStandardCustomer(array $overrides = []): Customer
{
    return Customer::query()->create(array_merge([
        'name' => 'Standard Buyer '.uniqid(),
        'phone' => '05'.random_int(10_000_000, 99_999_999),
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'legal_name_en' => 'Standard Buyer',
        'alt_id_type' => 'CRN',
        'alt_id_number' => '1010998899',
        'country_code' => 'SA',
        'street_name' => 'King Fahd Rd',
        'city' => 'Riyadh',
        'profile' => [
            'cr_number' => '1010998899',
            'country' => 'SA',
            'national_address' => [
                'building' => '12',
                'street' => 'King Fahd Rd',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postal_code' => '11564',
            ],
        ],
    ], $overrides));
}

function issueStandardViaLivewire(Customer $customer, bool $viaBuyerModalResume): Invoice
{
    $test = Livewire::actingAs(test()->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->call('selectDocumentCustomer', $customer->id);

    if ($viaBuyerModalResume) {
        $test->set('showStandardBuyerCompletionModal', false)
            ->call('addLineFromProduct', test()->product->id)
            ->set('buyerVatRegistered', false)
            ->set('buyerAltIdType', 'CRN')
            ->set('buyerAltIdNumber', '1010998899')
            ->set('addrBuilding', '12')
            ->set('addrStreet', 'King Fahd Rd')
            ->set('addrDistrict', 'Al Olaya')
            ->set('addrCity', 'Riyadh')
            ->set('addrPostal', '11564')
            ->set('standardBuyerPersistToCustomer', false)
            ->set('resumeIssueAfterBuyerCompletion', true)
            ->call('confirmStandardBuyerCompletion');
    } else {
        $test
            ->call('addLineFromProduct', test()->product->id)
            ->call('issue');
    }

    return Invoice::query()->where('document_number', 'like', 'STD-%')->latest('id')->firstOrFail();
}

it('stores standard document type when issuing without buyer modal', function () {
    $customer = completeStandardCustomer();

    $invoice = issueStandardViaLivewire($customer, viaBuyerModalResume: false);

    expect($invoice->document_type)->toBe(InvoiceDocumentType::Standard)
        ->and($invoice->payload)->toHaveKeys(['buyer_name', 'buyer_cr', 'buyer_national_address']);
});

it('stores standard document type when issuing after buyer completion modal resume', function () {
    $customer = Customer::query()->create([
        'name' => 'Modal Resume Buyer',
        'phone' => '05'.random_int(10_000_000, 99_999_999),
        'vat_number' => null,
        'referral_code' => '',
        'points_balance' => 0,
        'profile' => ['country' => 'SA', 'national_address' => []],
    ]);

    $invoice = issueStandardViaLivewire($customer, viaBuyerModalResume: true);

    expect($invoice->document_type)->toBe(InvoiceDocumentType::Standard)
        ->and($invoice->payload['buyer_cr'] ?? null)->toBe('1010998899');
});

it('prints standard tax invoice title and buyer section for default print', function () {
    $invoice = issueStandardViaLivewire(completeStandardCustomer(), viaBuyerModalResume: false);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice]))
        ->assertOk()
        ->assertSee(__('pos.receipt_title_standard_en'), false)
        ->assertSee(__('pos.receipt_title_standard_ar'), false)
        ->assertSee('1010998899', false)
        ->assertDontSee(__('pos.receipt_title_simplified_en'), false);
});

it('prints standard tax invoice title on cash layout when document type is standard', function () {
    $invoice = issueStandardViaLivewire(completeStandardCustomer(), viaBuyerModalResume: false);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->assertOk()
        ->assertSee(__('pos.receipt_title_standard_en'), false)
        ->assertSee(__('pos.receipt_title_standard_ar'), false)
        ->assertDontSee(__('pos.receipt_title_simplified_en'), false);
});

it('sends standard type to z-heart payload for standard invoices', function () {
    $business = BusinessSetting::singleton();

    $invoice = issueStandardViaLivewire(completeStandardCustomer(), viaBuyerModalResume: false);

    $request = new InvoiceSigningRequest(
        business: $business,
        documentNumber: $invoice->document_number,
        documentType: $invoice->document_type,
        invoiceCategory: 'standard',
        lines: [],
        invoicePayload: $invoice->payload ?? [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: (string) ($invoice->payload['invoice_uuid'] ?? \Illuminate\Support\Str::uuid()),
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $payload = app(ZHeartInvoicePayloadBuilder::class)->build($request);

    expect($payload['type'])->toBe('standard');
});