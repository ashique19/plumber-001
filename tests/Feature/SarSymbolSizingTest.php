<?php

use App\Livewire\Invoices\InvoiceHub;
use App\Livewire\Quotations\QuotationHub;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Business\BusinessContext;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('sizes the SAR SVG on the element so admin lists stay text-sized without print CSS', function () {
    $html = view('components.invoices.sar-symbol')->render();

    expect($html)
        ->toContain('width="1em"')
        ->toContain('height="1em"')
        ->toContain('fill="currentColor"')
        ->toContain('height:1em')
        ->toContain('width:auto')
        ->toContain('vertical-align:-0.125em')
        ->toContain('display:inline-block');

    seedDemoPaidPosOrder();

    $hub = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->html();

    expect($hub)
        ->toContain('invoice-sar-symbol__svg')
        ->toContain('width="1em"')
        ->toContain('height="1em"')
        ->toContain('vertical-align:-0.125em');

    Quotation::query()->create([
        'branch_id' => $this->owner->branch_id,
        'quotation_number' => 'QUO-SAR-SIZE',
        'invoice_mode' => 'simplified',
        'validity_until' => now()->addDays(7)->toDateString(),
        'payload' => [
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => $this->owner->id,
    ]);

    $quotations = Livewire::actingAs($this->owner, 'staff')
        ->test(QuotationHub::class)
        ->html();

    expect($quotations)
        ->toContain('invoice-sar-symbol__svg')
        ->toContain('width="1em"')
        ->toContain('height:1em');
});
