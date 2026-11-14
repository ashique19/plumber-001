<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Admin\AdminDashboard;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Models\StaffUser;
use App\Support\Reconcile\ReconcileIssueDateFilter;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->business = BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    \App\Business\BusinessContext::set($this->business);
});

it('does not render the removed today average order card', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(AdminDashboard::class)
        ->assertDontSee('আজকের গড় অর্ডার', false);
});

it('does not render invoices by type or signing queue summary cards', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        Livewire::actingAs($this->owner, 'staff')
            ->withQueryParams(['locale' => $locale])
            ->test(AdminDashboard::class)
            ->assertDontSee(__('dashboard.invoices_by_type', [], $locale), false)
            ->assertDontSee(__('dashboard.invoices_issued_hint', [], $locale), false)
            ->assertDontSee(__('dashboard.signing_queue_hint', [], $locale), false);
    }
});

it('renders bangla monthly report section with document labels and month title', function () {
    $tz = ReconcileIssueDateFilter::BUSINESS_TZ;
    $now = Carbon::now($tz);
    Carbon::setTestNow($now);

    $branch = Branch::query()->where('is_active', true)->orderBy('id')->firstOrFail();

    Invoice::query()->create([
        'branch_id' => $branch->id,
        'document_number' => 'STD-MONTH-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => InvoiceStatus::Issued,
        'issued_at' => $now->copy()->timezone('UTC'),
        'payload' => [
            'lines' => [
                [
                    'name' => 'Consulting',
                    'quantity' => 1,
                    'unit_price_ex_vat' => 200,
                    'vat_rate' => 15,
                ],
            ],
        ],
    ]);

    app()->setLocale('bn');

    $monthLabel = $now->copy()->startOfMonth()->locale('bn')->translatedFormat('F Y');

    Livewire::actingAs($this->owner, 'staff')
        ->withQueryParams(['locale' => 'bn'])
        ->test(AdminDashboard::class)
        ->assertSee(__('dashboard.monthly_report.title', [], 'bn'), false)
        ->assertSee(__('dashboard.monthly_report.doc_types.standard', [], 'bn'), false)
        ->assertSee(__('dashboard.monthly_report.doc_types.simplified', [], 'bn'), false)
        ->assertSee($monthLabel, false)
        ->assertSee('30.00', false)
        ->assertSee('230.00', false);

    Carbon::setTestNow();
});