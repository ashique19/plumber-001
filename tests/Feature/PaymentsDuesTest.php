<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceCollectionStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Payments\PaymentsDuesHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    seedDemoPaidPosOrder();
});

function issueManualInvoiceWithRetention(float $retentionPercent = 10): Invoice
{
    return Invoice::query()->create([
        'document_number' => 'MAN-'.uniqid(),
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => now()->addDays(30)->toDateString(),
        'payload' => [
            'buyer_name' => 'Acme Contracting',
            'buyer_phone' => '966500000001',
            'lines' => [
                [
                    'name' => 'Service',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 1000,
                    'vat_rate' => 15,
                ],
            ],
            'retention_percent' => $retentionPercent,
        ],
    ]);
}

it('renders payments dues route for owner', function () {
    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.payments.dues'))
        ->assertOk()
        ->assertSee(__('payments.title'), false);
});

it('tracks partial payments and splits retention due', function () {
    $invoice = issueManualInvoiceWithRetention(10);
    $ledger = app(InvoiceDuesLedgerService::class);

    $balance = $ledger->balanceFor($invoice);
    expect($balance['net_due'])->toBe(1035.0)
        ->and($balance['retention_due'])->toBe(115.0)
        ->and($balance['status'])->toBe(InvoiceCollectionStatus::Unpaid);

    $ledger->recordPayment(
        $invoice,
        now()->toDateString(),
        500,
        InvoiceArPaymentMethod::Bank,
        'TRF-1',
        false,
        $this->owner,
    );

    $partial = $ledger->balanceFor($invoice->fresh('payments'));
    expect($partial['principal_paid'])->toBe(500.0)
        ->and($partial['principal_outstanding'])->toBe(535.0)
        ->and($partial['retention_outstanding'])->toBe(115.0)
        ->and($partial['status'])->toBe(InvoiceCollectionStatus::Partial);

    $ledger->recordPayment(
        $invoice->fresh('payments'),
        now()->toDateString(),
        535,
        InvoiceArPaymentMethod::Cash,
        null,
        false,
        $this->owner,
    );

    $paidPrincipal = $ledger->balanceFor($invoice->fresh('payments'));
    expect($paidPrincipal['status'])->toBe(InvoiceCollectionStatus::Paid)
        ->and($paidPrincipal['retention_outstanding'])->toBe(115.0);

    $ledger->recordPayment(
        $invoice->fresh('payments'),
        now()->toDateString(),
        115,
        InvoiceArPaymentMethod::Card,
        'RET-1',
        true,
        $this->owner,
    );

    $fullySettled = $ledger->balanceFor($invoice->fresh('payments'));
    expect($fullySettled['retention_outstanding'])->toBe(0.0);
});

it('highlights selected customer and renders statement invoice hub actions', function () {
    $invoice = issueManualInvoiceWithRetention(0);
    $ledger = app(InvoiceDuesLedgerService::class);
    $customerKey = $ledger->customerOutstandingSummary()[0]['key'];

    Livewire::actingAs($this->owner, 'staff')
        ->test(PaymentsDuesHub::class)
        ->call('selectCustomer', $customerKey)
        ->assertSeeHtml('payments-dues-customer-row--selected')
        ->assertSeeHtml('aria-selected="true"')
        ->assertSeeHtml('data-testid="payments-statement-invoice"')
        ->assertSeeHtml('data-testid="hub-invoice-row-actions"')
        ->call('prepareRecordPayment', $invoice->id)
        ->assertSet('selectedInvoiceId', $invoice->id)
        ->assertSet('paymentAmount', '1150');
});

it('lists customer outstanding and records payment via livewire', function () {
    $invoice = issueManualInvoiceWithRetention(0);
    $ledger = app(InvoiceDuesLedgerService::class);

    expect($ledger->customerOutstandingSummary())->not->toBeEmpty();

    Livewire::actingAs($this->owner, 'staff')
        ->test(PaymentsDuesHub::class)
        ->set('selectedInvoiceId', $invoice->id)
        ->set('paymentAmount', '200')
        ->set('paymentMethod', 'bank')
        ->set('paymentReference', 'TEST')
        ->call('recordPayment')
        ->assertDispatched('toast');

    expect($invoice->fresh('payments')->payments)->toHaveCount(1);
});

it('treats paid pos invoices as collected on principal', function () {
    $ledger = app(InvoiceDuesLedgerService::class);
    $invoice = Invoice::query()->whereKey(seedDemoPaidPosOrder()->invoice_id)->firstOrFail();

    $balance = $ledger->balanceFor($invoice);
    expect($balance['net_due'])->toBeGreaterThan(0)
        ->and($balance['implied_pos_paid'])->toBe($balance['net_due'])
        ->and($balance['principal_outstanding'])->toBe(0.0);
});
