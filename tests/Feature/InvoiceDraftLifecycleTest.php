<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Hub\HubInvoiceDraftLifecycle;
use App\Support\Hub\HubInvoiceRowActions;
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
});

function draftInvoiceWithLines(string $number = 'DRAFT-LIFE-1'): Invoice
{
    return Invoice::query()->create([
        'document_number' => $number,
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => [
            'buyer_name' => 'Draft Buyer',
            'lines' => [
                [
                    'name' => 'Item',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 50,
                    'vat_rate' => 15,
                ],
            ],
        ],
    ]);
}

it('shows draft issue edit and delete actions in the hub more menu', function () {
    $draft = draftInvoiceWithLines();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', $draft->document_number)
        ->assertSee(__('invoices.action_issue_draft'), false)
        ->assertSee(__('invoices.action_edit'), false)
        ->assertSee(__('invoices.action_delete_draft'), false)
        ->assertSeeHtml('data-testid="hub-draft-action-issue"', false);
});

it('issues a draft from the hub when eligible', function () {
    config([
        'zheart.base_url' => '',
        'zheart.api_key' => '',
    ]);
    $draft = draftInvoiceWithLines('DRAFT-LIFE-ISSUE');

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('issueDraft', $draft->id)
        ->assertHasNoErrors();

    $fresh = $draft->fresh();
    expect($fresh->status)->toBe(InvoiceStatus::Issued);
});

it('blocks delete when draft was signed locally for zatca', function () {
    $draft = draftInvoiceWithLines('DRAFT-LIFE-SIGNED');
    $draft->forceFill([
        'signed_xml' => '<Invoice/>',
        'payload' => array_merge($draft->payload ?? [], [
            'signing_submission_status' => 'signed_locally',
        ]),
    ])->save();

    expect(HubInvoiceDraftLifecycle::canDelete($draft->fresh()))->toBeFalse()
        ->and(HubInvoiceRowActions::canDeleteDraft($draft->fresh()))->toBeFalse();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('deleteDraft', $draft->id)
        ->assertForbidden();

    expect(Invoice::query()->whereKey($draft->id)->exists())->toBeTrue();
});

it('hides draft lifecycle actions on issued invoice print view', function () {
    seedDemoPaidPosOrder();
    $issued = Invoice::query()->where('status', InvoiceStatus::Issued)->firstOrFail();

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $issued, 'layout' => 'cash']))
        ->assertOk()
        ->assertDontSee('data-testid="invoice-draft-lifecycle"', false)
        ->assertDontSeeHtml('data-testid="invoice-view-delete-draft"');
});

it('renders draft lifecycle bar on draft print preview', function () {
    $draft = draftInvoiceWithLines('DRAFT-LIFE-PREV');

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $draft, 'layout' => 'cash', 'preview' => 1]))
        ->assertOk()
        ->assertSeeHtml('data-testid="invoice-draft-lifecycle"', false)
        ->assertSee(__('invoices.action_issue_draft'), false)
        ->assertSeeHtml('data-testid="invoice-view-edit-draft"', false);
});

it('rejects hub issue for issued invoices', function () {
    seedDemoPaidPosOrder();
    $issued = Invoice::query()->where('status', InvoiceStatus::Issued)->firstOrFail();

    expect(HubInvoiceRowActions::canIssueDraft($issued))->toBeFalse();

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->call('issueDraft', $issued->id)
        ->assertForbidden();
});