<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\BusinessSetting;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders invoice create layout shell', function (): void {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->assertSee(__('invoices.create_heading'))
        ->assertSee(__('invoices.customer_information'))
        ->assertSee(__('invoices.invoice_summary'))
        ->assertDontSee(__('invoices.payment_received_section'))
        ->assertSee(__('invoices.document_lines_empty'))
        ->assertDontSee(__('invoices.add_another_product'));
});

it('renders standard invoice payment received sidebar', function (): void {
    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'standard'])
        ->assertSee(__('invoices.payment_received_section'))
        ->assertSee(__('invoices.payment_terms_net_30'));
});

it('renders quotation create layout shell', function (): void {
    Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->assertSee(__('quotations.create_title'))
        ->assertSee(__('quotations.terms_and_conditions'), false)
        ->assertSee(__('quotations.summary'))
        ->assertSee(__('quotations.information'));
});