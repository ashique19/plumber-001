<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StaffUser;
use App\Support\Invoicing\DocumentPaymentTerms;
use Carbon\Carbon;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->customer = Customer::query()->orderBy('id')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('keeps due date aligned with invoice date for cash terms on mount', function () {
  $today = now('Asia/Riyadh')->format('Y-m-d');

  Livewire::actingAs($this->owner, 'staff')
      ->test(IssueInvoice::class, ['mode' => 'standard'])
      ->assertSet('paymentTerms', 'cash')
      ->assertSet('supplyDate', $today)
      ->assertSet('dueDate', $today);
});

it('zeros amount received and sets due date when switching to credit terms', function () {
  $supply = now('Asia/Riyadh')->format('Y-m-d');
  $dueNet30 = Carbon::parse($supply, 'Asia/Riyadh')->addDays(30)->format('Y-m-d');

  Livewire::actingAs($this->owner, 'staff')
      ->test(IssueInvoice::class, ['mode' => 'standard'])
      ->set('paymentTerms', 'net_30')
      ->assertSet('amountReceived', '0.00')
      ->assertSet('dueDate', $dueNet30);
});

it('tracks grand total as amount received under cash terms when lines change', function () {
  $component = Livewire::actingAs($this->owner, 'staff')
      ->test(IssueInvoice::class, ['mode' => 'standard'])
      ->call('addLineFromProduct', $this->product->id);

  $grand = (float) $component->instance()->previewTotals()['total_inc_vat'];
  $component->assertSet('amountReceived', number_format($grand, 2, '.', ''));
});

it('auto switches to credit when cash terms receive a partial amount', function () {
  Livewire::actingAs($this->owner, 'staff')
      ->test(IssueInvoice::class, ['mode' => 'standard'])
      ->call('selectDocumentCustomer', $this->customer->id)
      ->call('addLineFromProduct', $this->product->id)
      ->set('amountReceived', '1.00')
      ->assertSet('paymentTerms', DocumentPaymentTerms::NET_30)
      ->assertSet('amountReceived', '1.00');
});

it('does not show payment received fields on quotation create', function () {
  Livewire::actingAs($this->owner, 'staff')
      ->test(ManageQuotation::class)
      ->assertSee(__('invoices.payment_terms_label'))
      ->assertDontSee(__('invoices.payment_received_section'));
});