<?php

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    app()->setLocale('en');
});

it('renders quotation modern meta in the invoice info grid without buyer split rows', function () {
    $quotation = Quotation::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'quotation_number' => 'HARNESS-QUO-001',
        'invoice_mode' => 'standard',
        'validity_until' => now()->addDays(30)->toDateString(),
        'payload' => [
            'payment_terms' => 'net_30',
            'customer' => [
                'name_en' => 'TaskenLab Company',
                'address_en' => 'Riyadh',
            ],
            'lines' => [
                ['name' => 'Valve', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
        ],
        'created_by_staff_user_id' => StaffUser::query()->first()?->id,
    ]);

    $html = $this->actingAs($this->owner, 'staff')
        ->get(route('admin.quotations.print', [
            'quotation' => $quotation,
            'template' => 'modern',
            'locale' => 'en',
        ]))
        ->assertOk()
        ->assertSee('invoice-print-info-grid', false)
        ->assertDontSee('invoice-print-info-grid-field', false)
        ->assertSee('Quotation number', false)
        ->assertSee('HARNESS-QUO-001', false)
        ->assertSee('Payment terms', false)
        ->assertSee('Net 30', false)
        ->assertDontSee('invoice-a4__buyer-table-split', false)
        ->assertDontSee('invoice-a4__buyer-table-split-row', false)
        ->content();

    expect($html)->toMatch('/invoice-print-info-grid__label--nowrap/');
});