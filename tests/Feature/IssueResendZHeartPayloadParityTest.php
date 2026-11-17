<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\Invoicing\Signing\InvoiceSigningRequest;
use App\Services\Invoicing\Signing\InvoiceSigningRequestFactory;
use App\Services\Invoicing\ZHeart\ZHeartInvoicePayloadBuilder;
use App\Support\Invoicing\InvoiceLineDataFactory;
use App\Support\Invoicing\ZHeartInvoicePayloadFromInvoice;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $business->forceFill([
        'settings' => array_merge($business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true],
        ]),
    ])->save();
    BusinessContext::set($business);
});

function parityStandardIssuedInvoice(): Invoice
{
    $issuedAt = Carbon::parse('2026-11-18 12:01:12', 'Asia/Riyadh')->utc();

    return Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-20261118-015112',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'due_date' => '2026-11-25',
        'icv' => 44,
        'invoice_hash' => 'hash-parity',
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'invoice_category' => 'standard',
            'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440099',
            'supply_date' => '2026-11-18',
            'payment_terms' => 'credit_30',
            'payment_method' => 'cash',
            'buyer_name' => 'TaskenLab Company',
            'buyer_vat_registered' => true,
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1012345678',
            'buyer_national_address' => [
                'building' => '12',
                'street' => 'King Fahd Road',
                'district' => 'Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12345',
                'country' => 'SA',
            ],
            'lines' => [
                [
                    'name' => 'Consulting',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 1000,
                    'vat_rate' => 15,
                ],
            ],
        ],
    ]);
}

it('builds identical z-heart payloads for issue clearance and resubmit', function () {
    $business = BusinessSetting::singleton();
    $invoice = parityStandardIssuedInvoice();

    $issueClearancePayload = ZHeartInvoicePayloadFromInvoice::build($invoice, $business);
    $resendPayload = ZHeartInvoicePayloadFromInvoice::build($invoice->fresh(), $business);

    expect($resendPayload)->toBe($issueClearancePayload)
        ->and($issueClearancePayload['issue_date'])->toBe('2026-11-18')
        ->and($issueClearancePayload['issue_time'])->toBe('12:01:12')
        ->and($issueClearancePayload['supply_date'])->toBe('2026-11-18')
        ->and($issueClearancePayload['uuid'])->toBe('550e8400-e29b-41d4-a716-446655440099');
});

it('diverged when issue used live now instead of persisted issued_at', function () {
    $business = BusinessSetting::singleton();
    $invoice = parityStandardIssuedInvoice();

    $resendPayload = ZHeartInvoicePayloadFromInvoice::build($invoice, $business);

    Carbon::setTestNow(Carbon::parse('2026-11-18 18:30:00', 'UTC'));

    $legacyRequest = new InvoiceSigningRequest(
        business: $business,
        documentNumber: $invoice->document_number,
        documentType: $invoice->document_type,
        invoiceCategory: 'standard',
        lines: InvoiceLineDataFactory::fromPayloadLines($invoice->payload['lines'] ?? []),
        invoicePayload: $invoice->payload ?? [],
        issueDateTimeUtc: Carbon::now('UTC'),
        invoiceUuid: (string) data_get($invoice->payload, 'invoice_uuid'),
        referencedInvoiceNumber: null,
        referencedInvoiceUuid: null,
        noteReason: null,
    );

    $legacyPayload = app(ZHeartInvoicePayloadBuilder::class)->build($legacyRequest);

    expect($legacyPayload['issue_time'])->not->toBe($resendPayload['issue_time']);
    Carbon::setTestNow();
});