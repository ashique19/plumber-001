<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\InvoicePrintClearance;
use App\Support\Invoicing\InvoicePrintPresenter;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $tenant = BusinessSetting::singleton();
    BusinessContext::set($tenant);
    seedDemoPaidPosOrder();
});

afterEach(fn () => BusinessContext::clear());

it('blocks official print for standard invoices until zatca cleared', function () {
    $invoice = Invoice::query()->firstOrFail();

    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill(['document_type' => InvoiceDocumentType::Standard]);
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'signing_submission_status' => 'signed_locally',
                'zheart_reporting_status' => 'pending',
            ]),
            'qr_tlv_base64' => 'cXI=',
        ])->save();
    });

    expect(InvoicePrintClearance::allowsOfficialPrint($invoice->fresh()))->toBeFalse();

    $business = BusinessSetting::singleton();
    $presented = InvoicePrintPresenter::fromInvoice($business, $invoice->fresh());

    expect($presented['qr_tlv_base64'])->toBeNull()
        ->and($presented['print_watermark'])->toBeTrue();
});

it('allows simplified print and z-heart qr when present', function () {
    $invoice = Invoice::query()->firstOrFail();

    expect(InvoicePrintClearance::allowsOfficialPrint($invoice))->toBeTrue();

    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill(['qr_tlv_base64' => 'cXIy'])->save();
    });

    $presented = InvoicePrintPresenter::fromInvoice(BusinessSetting::singleton(), $invoice->fresh());
    expect($presented['qr_tlv_base64'])->toBe('cXIy')
        ->and($presented['print_watermark'])->toBeFalse();
});