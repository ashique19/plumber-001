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

it('renders plain SAR text in admin lists and print components', function () {
    $html = view('components.invoices.sar-symbol')->render();

    expect($html)
        ->toContain('>SAR</span>')
        ->toContain('invoice-sar-symbol')
        ->not->toContain('<svg')
        ->not->toContain('invoice-sar-symbol__svg');

    seedDemoPaidPosOrder();

    $hub = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->html();

    expect($hub)
        ->toContain('invoice-sar-symbol')
        ->toContain('>SAR</span>')
        ->not->toContain('invoice-sar-symbol__svg');

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
        ->toContain('invoice-sar-symbol')
        ->toContain('>SAR</span>')
        ->not->toContain('invoice-sar-symbol__svg');
});
