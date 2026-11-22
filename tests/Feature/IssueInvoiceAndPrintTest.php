<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\Product;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->fakeZHeartSigning();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->product = Product::query()->where('sku', 'SRV-LEAK')->firstOrFail();
});

it('redirects to the A4 print view with autoprint after a successful issue and print', function () {
    $component = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('addLineFromProduct', $this->product->id)
        ->call('issueAndPrint')
        ->assertHasNoErrors();

    $invoice = Invoice::query()->latest('id')->firstOrFail();

    expect($invoice->isIssued())->toBeTrue();

    $component->assertRedirect(route('admin.invoices.print', [
        'invoice' => $invoice,
        'layout' => 'a4',
        'autoprint' => 1,
    ]));
});

it('behaves like issue when issue and print validation fails', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->call('issueAndPrint')
        ->assertHasErrors(['document']);

    expect(Invoice::query()->where('status', 'issued')->count())->toBe(0);
});

it('renders the issue and print action on the invoice create footer', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)
        ->toContain('data-testid="issue-and-print"')
        ->toContain(__('invoices.issue_and_print'));
});
