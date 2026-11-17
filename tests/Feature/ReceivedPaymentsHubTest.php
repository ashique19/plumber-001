<?php

use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Payments\ReceivedPaymentsHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\StaffUser;
use App\Support\Admin\AdminSidebarMenu;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('registers admin.payments.received and enables sidebar link', function () {
    expect(Route::has('admin.payments.received'))->toBeTrue();

    $menu = AdminSidebarMenu::build($this->owner, BusinessSetting::singleton(), request());
    $received = collect($menu['groups'])->pluck('items')->flatten(1)->firstWhere('id', 'received_payments');

    expect($received)->not->toBeNull()
        ->and($received['enabled'])->toBeTrue()
        ->and($received['href'])->toBe(route('admin.payments.received'));
});

it('renders received payments hub for owner with payment rows', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'INV-RP-001',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'buyer_name' => 'Acme Contracting',
            'lines' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    $paidOn = now()->toDateString();
    app(InvoiceDuesLedgerService::class)->recordPayment(
        $invoice,
        $paidOn,
        50,
        InvoiceArPaymentMethod::Cash,
        'RCPT-1',
        false,
        $this->owner,
    );

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.payments.received'))
        ->assertOk()
        ->assertSee(__('payments.received.title'), false)
        ->assertSee('INV-RP-001', false)
        ->assertSee('Acme Contracting', false)
        ->assertSee('50.00', false);

    Livewire::actingAs($this->owner, 'staff')
        ->test(ReceivedPaymentsHub::class)
        ->set('method', 'cash')
        ->assertSee('INV-RP-001', false)
        ->set('customerSearch', 'Acme')
        ->assertSee('Acme Contracting', false)
        ->set('method', 'bank')
        ->assertDontSee('INV-RP-001', false);
});

it('totals only payments inside the filtered date range', function () {
    $invoice = Invoice::query()->create([
        'document_number' => 'INV-RP-002',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'buyer_name' => 'Range Test',
            'lines' => [
                ['name' => 'Work', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
    ]);

    InvoicePayment::query()->create([
        'invoice_id' => $invoice->id,
        'paid_on' => now()->subDays(40)->toDateString(),
        'amount' => 10,
        'method' => InvoiceArPaymentMethod::Cash,
        'recorded_by_id' => $this->owner->id,
    ]);

    app(InvoiceDuesLedgerService::class)->recordPayment(
        $invoice,
        now()->toDateString(),
        20,
        InvoiceArPaymentMethod::Bank,
        null,
        false,
        $this->owner,
    );

    Livewire::actingAs($this->owner, 'staff')
        ->test(ReceivedPaymentsHub::class)
        ->assertSee(__('payments.received.total').': 20.00', false);
});