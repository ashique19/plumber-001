<?php

use App\Business\BusinessContext;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Invoices\InvoiceHub;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
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
    seedDemoPaidPosOrder();
});

it('orders the invoice hub latest first by issued_at created_at and id', function () {
    $issuedAt = now()->startOfDay()->addHours(12);

    $olderSameDay = Invoice::query()->create([
        'document_number' => 'SORT-ISSUED-OLDER',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'created_at' => $issuedAt->copy()->subHour(),
        'payload' => ['buyer_name' => 'Sort Buyer', 'lines' => []],
    ]);

    $newerSameDay = Invoice::query()->create([
        'document_number' => 'SORT-ISSUED-NEWER',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $issuedAt,
        'created_at' => $issuedAt->copy()->addHour(),
        'payload' => ['buyer_name' => 'Sort Buyer', 'lines' => []],
    ]);

    $olderDraft = Invoice::query()->create([
        'document_number' => 'SORT-DRAFT-OLDER',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'created_at' => now()->subDays(2),
        'payload' => ['buyer_name' => 'Sort Buyer', 'lines' => []],
    ]);

    $newerDraft = Invoice::query()->create([
        'document_number' => 'SORT-DRAFT-NEWER',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'created_at' => now(),
        'payload' => ['buyer_name' => 'Sort Buyer', 'lines' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', 'SORT-')
        ->assertSeeHtml('aria-sort="descending"')
        ->assertSeeHtml("sortByColumn('issued_at')")
        ->assertSeeInOrder([
            'SORT-ISSUED-NEWER',
            'SORT-ISSUED-OLDER',
            'SORT-DRAFT-NEWER',
            'SORT-DRAFT-OLDER',
        ], false);
});

it('preserves user chosen invoice sort direction', function () {
    Invoice::query()->create([
        'document_number' => 'SORT-ASC-A',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['buyer_name' => 'Alpha', 'lines' => []],
    ]);

    Invoice::query()->create([
        'document_number' => 'SORT-ASC-Z',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => InvoiceStatus::Draft,
        'payload' => ['buyer_name' => 'Zulu', 'lines' => []],
    ]);

    Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->set('search', 'SORT-ASC-')
        ->call('sortByColumn', 'buyer_name')
        ->assertSet('sortBy', 'buyer_name')
        ->assertSet('sortDir', 'asc')
        ->assertSeeInOrder(['SORT-ASC-A', 'SORT-ASC-Z'], false)
        ->call('sortByColumn', 'buyer_name')
        ->assertSet('sortDir', 'desc')
        ->assertSeeInOrder(['SORT-ASC-Z', 'SORT-ASC-A'], false);
});
