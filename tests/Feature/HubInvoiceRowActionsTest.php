<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceZatcaBadge;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

afterEach(function () {
    Auth::guard('staff')->logout();
    BusinessContext::clear();
});

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    seedDemoPaidPosOrder();
});

it('allows view for all invoices', function () {
    $issued = Invoice::query()->firstOrFail();
    $draft = Invoice::query()->create([
        'document_number' => 'DRAFT-ACTION-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['lines' => []],
    ]);

    expect(HubInvoiceRowActions::canView($issued))->toBeTrue()
        ->and(HubInvoiceRowActions::canView($draft))->toBeTrue();
});

it('allows edit and delete only for drafts', function () {
    $draft = Invoice::query()->create([
        'document_number' => 'DRAFT-ACTION-2',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['lines' => []],
    ]);
    $issued = Invoice::query()->firstOrFail();

    expect(HubInvoiceRowActions::canEdit($draft))->toBeTrue()
        ->and(HubInvoiceRowActions::canDeleteDraft($draft))->toBeTrue()
        ->and(HubInvoiceRowActions::canEdit($issued))->toBeFalse()
        ->and(HubInvoiceRowActions::canDeleteDraft($issued))->toBeFalse();
});

it('allows print and download only for valid generated documents', function () {
    $simplified = Invoice::query()->firstOrFail();
    expect(HubInvoiceRowActions::canDownloadOrPrint($simplified))->toBeTrue();

    Invoice::withoutEvents(function () use ($simplified): void {
        $simplified->forceFill(['document_type' => InvoiceDocumentType::Standard]);
        $simplified->forceFill([
            'payload' => array_merge($simplified->payload ?? [], [
                'zheart_reporting_status' => 'pending',
                'signing_submission_status' => 'signed_locally',
            ]),
        ])->save();
    });

    expect(HubInvoiceRowActions::canDownloadOrPrint($simplified->fresh()))->toBeFalse()
        ->and(HubInvoiceRowActions::showAwaitingClearanceControls($simplified->fresh()))->toBeTrue();
});

it('allows record payment only when due amount is positive', function () {
    $invoice = Invoice::query()->firstOrFail();

    expect(HubInvoiceRowActions::canRecordPayment($invoice, 0))->toBeFalse()
        ->and(HubInvoiceRowActions::canRecordPayment($invoice, 12.5))->toBeTrue();
});

it('allows zatca resend for any issued invoice that is not yet zatca-success when configured', function () {
    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
    ]);
    $this->business = BusinessSetting::singleton();
    $this->business->forceFill([
        'settings' => array_merge($this->business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true],
        ]),
    ])->save();

    $invoice = Invoice::query()->firstOrFail();

    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'signing_submission_status' => 'not_submitted',
                'zheart_remote_submitted' => false,
            ]),
        ])->save();
    });

    expect(HubInvoiceRowActions::canResendZatca($invoice->fresh()))->toBeTrue();

    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'zheart_remote_submitted' => true,
                'zheart_reporting_status' => 'reported',
                'zheart_zatca' => ['status' => 'reported'],
                'signing_submission_status' => 'submitted',
            ]),
        ])->save();
    });

    expect(HubInvoiceRowActions::canResendZatca($invoice->fresh()))->toBeFalse();

    $standardPending = Invoice::query()->create([
        'document_number' => 'STD-RESEND-PENDING',
        'document_type' => \App\Enums\InvoiceDocumentType::Standard,
        'status' => \App\Enums\InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [],
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
            'signing_submission_status' => 'signed_locally',
        ],
    ]);

    expect(HubInvoiceZatcaBadge::badgeKey($standardPending))->toBe(HubInvoiceZatcaBadge::ClearancePending)
        ->and(HubInvoiceRowActions::canResendZatca($standardPending))->toBeTrue();

    $simplifiedPending = Invoice::query()->create([
        'document_number' => 'SIM-RESEND-PENDING',
        'document_type' => \App\Enums\InvoiceDocumentType::Simplified,
        'status' => \App\Enums\InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [],
            'zheart_remote_submitted' => false,
            'zheart_reporting_status' => 'pending',
            'signing_submission_status' => 'signed_locally',
        ],
    ]);

    expect(HubInvoiceZatcaBadge::badgeKey($simplifiedPending))->toBe(HubInvoiceZatcaBadge::ReportingPending)
        ->and(HubInvoiceRowActions::canResendZatca($simplifiedPending))->toBeTrue();

    $remotePending = Invoice::query()->create([
        'document_number' => 'STD-REMOTE-ZATCA',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [],
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'pending',
            'signing_submission_status' => 'submitted',
        ],
    ]);

    expect(\App\Support\Hub\HubInvoiceZatcaTab::isFailed($remotePending))->toBeFalse()
        ->and(HubInvoiceRowActions::canResendZatca($remotePending))->toBeTrue();
});

it('shows resend as a primary row action on the failed tab', function () {
    config([
        'zheart.base_url' => 'https://z-heart.test',
        'zheart.api_key' => 'zh_test1234.AbcdEfGhIjKlMnOpQrStUvWxYz0123456789',
    ]);
    $business = BusinessSetting::singleton();
    $business->forceFill([
        'settings' => array_merge($business->settings ?? [], [
            'invoicing' => ['integration_enabled' => true],
        ]),
    ])->save();

    $invoice = Invoice::query()->firstOrFail();
    Invoice::withoutEvents(function () use ($invoice): void {
        $invoice->forceFill([
            'payload' => array_merge($invoice->payload ?? [], [
                'signing_submission_status' => 'signed_locally',
                'zheart_remote_submitted' => false,
                'zheart_reporting_status' => 'pending',
            ]),
            'signed_xml' => '<Invoice/>',
        ])->save();
    });

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('zatcaTab', \App\Support\Hub\HubInvoiceZatcaTab::FAILED)
        ->assertSeeHtml('data-testid="hub-row-action-zatca-resubmit"', false)
        ->assertSee(__('invoices.zatca_resubmit_all_pending'), false);
});

it('rejects hub delete draft for issued invoices server-side', function () {
    $issued = Invoice::query()->firstOrFail();

    expect(Invoice::query()->whereKey($issued->id)->exists())->toBeTrue();

    try {
        Livewire::actingAs($this->owner, 'staff')
            ->test(InvoiceHub::class)
            ->call('deleteDraft', $issued->id);
        $blocked = false;
    } catch (\Throwable) {
        $blocked = true;
    }

    expect($blocked || Invoice::query()->whereKey($issued->id)->exists())->toBeTrue();
});

it('deletes draft invoices from hub', function () {
    $draft = Invoice::query()->create([
        'document_number' => 'DRAFT-DEL-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['lines' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('deleteDraft', $draft->id)
        ->assertHasNoErrors();

    expect(Invoice::query()->whereKey($draft->id)->exists())->toBeFalse();
});