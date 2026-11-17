<?php

use App\Livewire\Invoices\IssueInvoice;
use App\Livewire\Quotations\ManageQuotation;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

$documentKeys = [
    'invoices.document_meta',
    'invoices.field_document_number',
    'invoices.document_number_pending',
    'invoices.invoice_date',
    'invoices.invoice_type',
    'invoices.type.standard_tax',
    'invoices.type.simplified_tax',
    'invoices.payment_terms_label',
    'invoices.payment_terms_cash',
    'invoices.payment_terms_credit',
    'invoices.customer_information',
    'invoices.add_products',
    'invoices.line_product',
    'invoices.summary_discount',
    'invoices.line_total_inc_vat',
    'invoices.add_another_product',
    'invoices.invoice_summary',
    'invoices.summary_subtotal',
    'invoices.summary_taxable',
    'invoices.grand_total',
    'invoices.payment_details',
    'invoices.payment_method_label',
    'invoices.amount_received',
    'invoices.due_amount',
    'invoices.cancel',
    'invoices.save_draft',
    'invoices.preview_document',
    'invoices.subtotal_ex_vat',
    'invoices.total_vat',
    'quotations.field_document_number',
    'quotations.terms_and_conditions',
    'quotations.summary',
    'quotations.information',
    'quotations.validity_period',
    'quotations.status',
    'quotations.status_draft',
    'quotations.preview_document',
    'quotations.create_submit',
];

it('translates invoice and quotation create labels in bn and ar', function () use ($documentKeys) {
    foreach (['bn', 'ar'] as $locale) {
        foreach ($documentKeys as $key) {
            expect(__($key, [], $locale))
                ->not->toBe(__($key, [], 'en'), "{$key} still English in {$locale}");
        }
    }
});

it('renders bangla invoice create copy instead of leftover english', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(IssueInvoice::class, ['mode' => 'simplified'])
        ->html();

    expect($html)
        ->toContain(__('invoices.document_meta', [], 'bn'))
        ->toContain(__('invoices.add_products', [], 'bn'))
        ->not->toContain('Invoice details')
        ->not->toContain('Assigned on save');
});

it('renders quotation terms without double-escaped ampersand', function () {
    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(ManageQuotation::class)
        ->html();

    expect($html)->not->toContain('Terms &amp;amp; conditions')
        ->and($html)->not->toContain('Terms &amp; conditions');
});

it('does not hard-code leftover english phrases in create blades', function () {
    $files = [
        resource_path('views/livewire/invoices/issue-invoice.blade.php'),
        resource_path('views/livewire/quotations/manage-quotation.blade.php'),
        resource_path('views/components/invoices/document-product-lines-create.blade.php'),
        resource_path('views/components/documents/create-footer.blade.php'),
        resource_path('views/components/documents/summary-card.blade.php'),
        resource_path('views/components/invoices/document-payment-details.blade.php'),
        resource_path('views/components/invoices/document-customer-card.blade.php'),
    ];

    $banned = [
        'Invoice details',
        'Assigned on save',
        'Invoice No.',
        'Add products',
        'Preview invoice',
        'Quotation No.',
        'Terms & conditions',
        'Create quotation',
        'Subtotal (ex VAT)',
        'Total VAT',
    ];

    foreach ($files as $file) {
        if (! is_file($file)) {
            continue;
        }
        $source = (string) file_get_contents($file);
        foreach ($banned as $phrase) {
            expect($source)->not->toContain($phrase, "{$file} still has hardcoded “{$phrase}”");
        }
    }
});
