<?php

use App\Enums\InvoiceDocumentType;
use App\Livewire\Invoices\IssueInvoice;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Services\InvoiceIssueService;
use App\Business\BusinessContext;
use App\Livewire\Invoices\InvoiceHub;
use App\Support\Invoicing\InvoicePrintPresenter;
use App\Support\Themes\PlumberThemePreset;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    config([
        'zheart.base_url' => '',
        'zheart.api_key' => '',
    ]);
});

it('issues simplified invoice without zheart credentials', function () {
    $before = Invoice::query()->count();

    Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->set('lines', [
            ['name' => 'Leak repair', 'name_ar' => '', 'quantity' => '1', 'unit_price' => '100', 'vat_rate' => '15', 'product_id' => null, 'unit_label' => ''],
        ])
        ->call('issue')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'warning', persistent: true)
        ->assertDispatched('toast', type: 'success');

    expect(Invoice::query()->count())->toBe($before + 1);

    $invoice = Invoice::query()->latest('id')->firstOrFail();
    expect($invoice->status->value)->toBe('issued')
        ->and($invoice->signed_xml)->toBeNull()
        ->and($invoice->payload['signing_submission_status'] ?? null)->toBe('not_submitted')
        ->and($invoice->payload['zheart_reporting_status'] ?? null)->toBe('pending');
});

it('issues standard invoice without zheart via service', function () {
    $tenant = BusinessSetting::query()->create([
        'name' => 'No ZHeart',
        'settings' => PlumberThemePreset::settings('No ZHeart', 'بدون'),
    ]);
    BusinessContext::set($tenant);
    $branch = Branch::query()->firstOrFail();

    $draft = app(InvoiceIssueService::class)->issueDraft(
        $branch,
        'STD-NO-ZH-1',
        InvoiceDocumentType::Standard,
        [['name' => 'Pipe work', 'quantity' => 1, 'unit_price_ex_vat' => 250, 'vat_rate' => 15]],
    );

    $draft->forceFill([
        'payload' => array_merge($draft->payload ?? [], [
            'buyer_name' => 'Buyer LLC',
            'buyer_vat' => '300000000000003',
            'buyer_phone' => '+966500000001',
            'buyer_email' => 'buyer@example.test',
            'buyer_country' => 'SA',
            'buyer_due_date' => '2026-12-01',
            'buyer_national_address' => [
                'building' => '10',
                'street' => 'King Fahd Rd',
                'city' => 'Riyadh',
                'postal_code' => '11564',
                'country' => 'SA',
            ],
        ]),
    ])->save();

    $outcome = app(InvoiceIssueService::class)->issue($draft->fresh());

    expect($outcome->signingSucceeded)->toBeFalse()
        ->and($outcome->invoice->status->value)->toBe('issued')
        ->and($outcome->invoice->due_date?->format('Y-m-d'))->toBe('2026-12-01')
        ->and($outcome->invoice->payload['buyer_phone'] ?? null)->toBe('+966500000001');

    BusinessContext::clear();
});

it('shows zatca not ready notice above issue button when creds missing', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)->toContain('invoice-zatca-notice')
        ->and($html)->toContain(__('invoices.zatca_not_ready_notice', locale: 'bn'));
});

it('shows zatca not sent badge on invoice hub list', function () {
    app()->setLocale('bn');

    $invoice = Invoice::query()->create([
        'branch_id' => \App\Models\Branch::query()->first()?->id,
        'document_number' => 'SIMP-ZATCA-1',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => \App\Enums\InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
            'signing_submission_status' => 'not_submitted',
            'zheart_reporting_status' => 'pending',
        ],
    ]);

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(InvoiceHub::class)
        ->html();

    expect($html)->toContain(__('invoices.signing.zatca_not_sent', locale: 'bn'))
        ->and($html)->toContain($invoice->document_number);
});

it('renders issue loading label on simplified issue form', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)->toContain(__('loading.issue', locale: 'bn'))
        ->and($html)->toContain('wire:target="issue"');
});

it('shows zatca not sent banner on print view', function () {
    $invoice = Invoice::query()->create([
        'branch_id' => \App\Models\Branch::query()->first()?->id,
        'document_number' => 'SIMP-PRINT-ZATCA',
        'document_type' => InvoiceDocumentType::Simplified,
        'status' => \App\Enums\InvoiceStatus::Issued,
        'issued_at' => now(),
        'payload' => [
            'lines' => [['name' => 'Svc', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15]],
            'signing_submission_status' => 'not_submitted',
        ],
    ]);

    $this->actingAs($this->owner, 'staff')
        ->get(route('admin.invoices.print', ['invoice' => $invoice, 'layout' => 'cash']))
        ->assertOk()
        ->assertSee(__('invoices.signing.zatca_not_sent'), false);
});

it('includes buyer mobile and due date on A4 print data', function () {
    $business = BusinessSetting::singleton();
    $invoice = Invoice::query()->create([
        'branch_id' => Branch::query()->first()?->id,
        'document_number' => 'STD-PRINT-1',
        'document_type' => InvoiceDocumentType::Standard,
        'status' => \App\Enums\InvoiceStatus::Issued,
        'issued_at' => now(),
        'due_date' => '2026-12-15',
        'payload' => [
            'lines' => [
                ['name' => 'Service', 'quantity' => 1, 'unit_price_ex_vat' => 100, 'vat_rate' => 15],
            ],
            'buyer_name' => 'Buyer',
            'buyer_phone' => '+966511122233',
            'buyer_email' => 'a@b.test',
            'buyer_country' => 'SA',
        ],
    ]);

    $presented = InvoicePrintPresenter::fromInvoice($business, $invoice);

    expect($presented['due_date'])->toBe('2026-12-15')
        ->and($presented['customer']['phone'] ?? null)->toBe('+966511122233');
});