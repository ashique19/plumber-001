<?php

use App\Enums\InvoiceDocumentType;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\BusinessSetting;
use App\Services\InvoiceIssueService;
use App\Business\BusinessContext;
use App\Support\Themes\PlumberThemePreset;

it('issues a simplified invoice via zheart sign and stores receipt fields', function () {
    $tenant = BusinessSetting::query()->create([
        'name' => 'ZHeart Issue',
        'settings' => PlumberThemePreset::settings('ZHeart Issue', 'زد هارت'),
    ]);
    BusinessContext::set($tenant);

    $branch = Branch::query()->create([
        'code' => 'sign-1',
        'name' => 'Sign Branch',
    ]);

    $invoice = app(InvoiceIssueService::class)->issueDraft(
        $branch,
        'POS-9001',
        InvoiceDocumentType::Simplified,
        [['name' => 'Latte', 'quantity' => 1, 'unit_price_ex_vat' => 20, 'vat_rate' => 15]],
    );

    $issued = app(InvoiceIssueService::class)->issue($invoice);

    expect($issued->status->value)->toBe('issued')
        ->and($issued->signed_xml)->toContain('Invoice')
        ->and($issued->invoice_hash)->toBe('aGFzaA==')
        ->and($issued->qr_tlv_base64)->toBe('cXI=')
        ->and($issued->icv)->toBe(1)
        ->and($issued->payload['invoice_uuid'])->toBe('550e8400-e29b-41d4-a716-446655440099');

    BusinessContext::clear();
});
