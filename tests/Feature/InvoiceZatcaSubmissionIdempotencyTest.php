<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoiceIssueService;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceZatcaTab;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();

    $tenant = BusinessSetting::singleton();
    $tenant->forceFill([
        'settings' => array_merge($tenant->settings ?? [], [
            'invoicing' => ['integration_enabled' => true, 'default_document_type' => 'simplified'],
        ]),
    ])->save();
    BusinessContext::set($tenant);

    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test_key',
        'zheart.auto_submit_signed' => true,
        'zheart.retry_times' => 3,
    ]);
});

function zatcaDraft(string $number, InvoiceDocumentType $type): Invoice
{
    $branch = Branch::query()->firstOrFail();

    return app(InvoiceIssueService::class)->issueDraft(
        $branch,
        $number,
        $type,
        [['name' => 'Service', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15]],
    );
}

it('issues simplified invoice with a single Z-Heart report call', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/report' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'invoice_hash' => 'hash',
                'qr' => 'qr',
                'uuid' => '550e8400-e29b-41d4-a716-446655440099',
                'icv' => 1,
                'pih' => '',
                'zatca' => ['status' => 'reported'],
                'reporting_status' => 'reported',
            ],
        ], 200),
    ]);

    $draft = zatcaDraft('SIMP-IDEM-1', InvoiceDocumentType::Simplified);

    $first = app(InvoiceIssueService::class)->issue($draft);
    $second = app(InvoiceIssueService::class)->issue($first->invoice->fresh());

    expect($second->invoice->isIssued())->toBeTrue()
        ->and($second->invoice->payload['zheart_remote_submitted'])->toBeTrue();

    Http::assertSentCount(1);
});

it('skips resubmit when invoice was already reported', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'SIMP-DONE',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'reported',
            'zheart_zatca' => ['status' => 'reported'],
            'invoice_uuid' => '550e8400-e29b-41d4-a716-446655440010',
            'lines' => [['name' => 'x', 'quantity' => 1, 'unit_price_ex_vat' => 1, 'vat_rate' => 15]],
        ],
    ]);

    Http::fake();

    $result = app(InvoiceZatcaResubmitService::class)->resubmit($invoice);

    expect($result->code)->toBe('already_success');
    Http::assertNothingSent();
});

it('surfaces standard clear failure on the invoice payload', function () {
    Http::fake([
        'https://z-heart.test/api/v1/invoices/clear' => Http::response([
            'status' => 'ok',
            'data' => [
                'signed_xml' => base64_encode('<Invoice/>'),
                'invoice_hash' => 'hash',
                'qr' => 'qr',
                'uuid' => '550e8400-e29b-41d4-a716-446655440011',
                'icv' => 2,
                'pih' => '',
                'zatca' => [
                    'status' => 'rejected',
                    'message' => 'Buyer VAT invalid',
                ],
                'reporting_status' => 'rejected',
            ],
        ], 200),
    ]);

    $draft = zatcaDraft('STD-FAIL-1', InvoiceDocumentType::Standard);
    $draft->forceFill([
        'payload' => array_merge($draft->payload ?? [], [
            'buyer_name' => 'Buyer',
            'buyer_vat' => '300000000000003',
            'buyer_cr' => '1012345678',
            'supply_date' => '2026-11-18',
            'buyer_national_address' => [
                'building' => '12',
                'street' => 'King Fahd Road',
                'district' => 'Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12345',
            ],
        ]),
    ])->save();

    $outcome = app(InvoiceIssueService::class)->issue($draft->fresh());
    $issued = $outcome->invoice->fresh();

    expect($issued->payload['zheart_last_error'] ?? null)->toBe('Buyer VAT invalid')
        ->and(HubInvoiceZatcaTab::shortError($issued))->toBe('Buyer VAT invalid');
});