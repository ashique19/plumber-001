<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Services\Business\DashboardMonthlyInvoiceReportService;
use App\Support\Reconcile\ReconcileIssueDateFilter;
use Illuminate\Support\Carbon;

it('aggregates net vat with credit notes subtracted and respects branch', function () {
    $tz = ReconcileIssueDateFilter::BUSINESS_TZ;
  $month = Carbon::parse('2026-08-15', $tz)->startOfMonth();
    Carbon::setTestNow($month->copy()->addDays(10));

    $branchA = Branch::query()->create(['code' => 'a', 'name' => 'A']);
    $branchB = Branch::query()->create(['code' => 'b', 'name' => 'B']);
    $issuedAt = $month->copy()->addDays(5)->timezone('UTC');

  $line = fn (): array => [
        'lines' => [
            [
                'name' => 'Item',
                'quantity' => 1,
                'unit_price_ex_vat' => 100,
                'vat_rate' => 15,
            ],
        ],
    ];

    Invoice::query()->create([
        'branch_id' => $branchA->id,
        'document_number' => 'SIMP-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'payload' => $line(),
    ]);

    Invoice::query()->create([
        'branch_id' => $branchA->id,
        'document_number' => 'CN-1',
        'document_type' => InvoiceDocumentType::CreditNote,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'payload' => $line(),
    ]);

    Invoice::query()->create([
        'branch_id' => $branchB->id,
        'document_number' => 'SIMP-2',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'payload' => $line(),
    ]);

    /** @var DashboardMonthlyInvoiceReportService $service */
    $service = app(DashboardMonthlyInvoiceReportService::class);
    $business = \App\Models\BusinessSetting::query()->create([
        'name' => 'Report Co',
        'settings' => \App\Support\Themes\PlumberThemePreset::settings('Report Co', 'شركة'),
    ]);

    $report = $service->report($business, $branchA, 'en');

    $august = collect($report)->firstWhere('month_key', '2026-08');
    expect($august)->not->toBeNull()
        ->and($august['net_vat'])->toBe(0.0)
        ->and($august['by_type']['simplified']['count'])->toBe(1)
        ->and($august['by_type']['simplified']['vat'])->toBe(15.0)
        ->and($august['by_type']['credit_note']['count'])->toBe(1)
        ->and($august['signed_vat_by_type']['simplified'])->toBe(15.0)
        ->and($august['signed_vat_by_type']['credit_note'])->toBe(-15.0);

    Carbon::setTestNow();
});