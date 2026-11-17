<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceLifecycleTab;
use App\Support\Hub\HubInvoiceRowActions;
use App\Support\Hub\HubInvoiceZatcaBadge;
use App\Support\Hub\HubInvoiceZatcaTab;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

function makeRemotePendingStandardInvoice(string $number = 'STD-REMOTE-PENDING'): Invoice
{
    return Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => $number,
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [
                ['name' => 'Pipe', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'signing_submission_status' => 'submitted',
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'pending',
            'zheart_zatca' => ['status' => 'pending', 'uuid' => '550e8400-e29b-41d4-a716-446655440099'],
        ],
    ]);
}

it('does not treat a pending zheart_zatca response blob as success', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->value('id'),
        'document_number' => 'STD-PENDING-BLOB',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'signed_xml' => '<Invoice/>',
        'payload' => [
            'lines' => [],
            'zheart_remote_submitted' => true,
            'zheart_reporting_status' => 'pending',
            'zheart_zatca' => ['status' => 'pending', 'uuid' => '550e8400-e29b-41d4-a716-446655440000'],
        ],
    ]);

    expect(HubInvoiceZatcaBadge::badgeKey($invoice))->toBe(HubInvoiceZatcaBadge::ClearancePending)
        ->and(HubInvoiceZatcaTab::isSuccess($invoice))->toBeFalse()
        ->and(HubInvoiceZatcaTab::needsZatcaResubmit($invoice))->toBeTrue();
});

it('allows resend for issued invoices stuck in clearance or reporting pending', function () {
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

    $invoice = makeRemotePendingStandardInvoice();

    expect(HubInvoiceZatcaBadge::badgeKey($invoice))->toBe(HubInvoiceZatcaBadge::ClearancePending)
        ->and(HubInvoiceZatcaTab::needsZatcaResubmit($invoice))->toBeTrue()
        ->and(HubInvoiceRowActions::canResendZatca($invoice))->toBeTrue()
        ->and(HubInvoiceZatcaTab::canResend($invoice))->toBeTrue();
});

it('renders the invoice hub awaiting clearance list with resubmit row action and bulk retry', function () {
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

    $invoice = makeRemotePendingStandardInvoice('STD-20261117-231353');

    $response = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices', ['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE]));

    $response->assertOk()
        ->assertSee($invoice->document_number, false)
        ->assertSee(__('invoices.zatca_badge.clearance_pending'), false);

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE])
        ->test(InvoiceHub::class)
        ->assertSet('lifecycleTab', HubInvoiceLifecycleTab::AWAITING_CLEARANCE)
        ->set('search', $invoice->document_number)
        ->assertSee(__('invoices.action_send_for_clearance'), false)
        ->assertSeeHtml('data-testid="hub-row-action-zatca-resubmit"', false)
        ->assertSeeHtml('data-testid="hub-zatca-resubmit-all-pending"', false);
});

it('shows zatca resubmit icon on awaiting clearance lifecycle tab', function () {
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

    $invoice = makeRemotePendingStandardInvoice('STD-HUB-BTN-1');
    $label = __('invoices.action_send_for_clearance');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['lifecycle' => HubInvoiceLifecycleTab::AWAITING_CLEARANCE])
        ->test(InvoiceHub::class)
        ->set('lifecycleTab', HubInvoiceLifecycleTab::AWAITING_CLEARANCE)
        ->set('search', $invoice->document_number)
        ->assertSee($label, false)
        ->assertSeeHtml('data-testid="hub-row-action-zatca-resubmit"', false)
        ->assertSeeHtml('data-testid="hub-zatca-resubmit-all-pending"', false);
});

it('shows disabled resubmit control when z-heart is not configured', function () {
    config([
        'zheart.base_url' => null,
        'zheart.api_key' => null,
    ]);

    $invoice = makeRemotePendingStandardInvoice('STD-HUB-DIS-1');

    expect(HubInvoiceRowActions::forInvoice($invoice, 0.0)['resend_zatca'])->toBeTrue()
        ->and(HubInvoiceRowActions::canResendZatca($invoice))->toBeFalse();

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $invoice->document_number)
        ->html();

    expect($html)
        ->toContain('data-testid="hub-row-action-zatca-resubmit-disabled"')
        ->toContain('title="'.e(__('invoices.zatca_resubmit_disabled_no_zheart')).'"');
});