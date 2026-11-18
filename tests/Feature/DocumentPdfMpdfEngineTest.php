<?php

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\Invoice;
use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\PrintSheetFactory;
use Database\Seeders\DemoStaffSeeder;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    (new DemoStaffSeeder)->run();
    $business = BusinessSetting::singleton();
    $settings = $business->settings;
    data_set($settings, 'legal_entity.name_en', 'RAZAN HADI ALHAYANI Establishment');
    data_set($settings, 'legal_entity.name_ar', 'مؤسسة رزان هادي الحياني');
    data_set($settings, 'legal_entity.national_address', [
        'building' => '7098',
        'street' => 'Ahmad Ibn Al Hussain Ibn Shuqair St',
        'district' => 'Al Malaz',
        'city' => 'Riyadh',
        'postal_code' => '12629',
        'additional' => '3652',
        'country' => 'SA',
        'street_ar' => 'أحمد بن الحسين بن شقير',
        'district_ar' => 'حي الملز',
        'city_ar' => 'الرياض',
    ]);
    $business->settings = $settings;
    $business->save();

    $this->branchId = Branch::query()->value('id');
});

it('renders simplified and standard invoices with mPDF on one page without mpdf failure logs', function () {
    Event::fake([MessageLogged::class]);

    $cases = [
        [
            'type' => InvoiceDocumentType::Simplified,
            'number' => 'HARNESS-SIMP-MPDF',
            'payload' => [
                'lines' => [
                    ['name' => 'Test Modal Product', 'name_ar' => 'منتج اختبار', 'quantity' => 1, 'unit_price_ex_vat' => 10, 'vat_rate' => 15],
                ],
            ],
        ],
        [
            'type' => InvoiceDocumentType::Standard,
            'number' => 'HARNESS-STD-MPDF',
            'payload' => [
                'lines' => [
                    ['name' => 'QA Product A', 'name_ar' => 'منتج اختبار أ', 'quantity' => 1, 'unit_price_ex_vat' => 25, 'vat_rate' => 15],
                ],
                'buyer_name_en' => 'TaskenLab Company',
                'buyer_name_ar' => 'شركة تاسكنلاب',
                'buyer_email' => 'taulutwashi@gmail.com',
                'buyer_phone' => '+966560231362',
                'buyer_vat' => '314430837500003',
                'buyer_national_address' => [
                    'building' => '7780',
                    'street' => 'Prince Abdulaziz bin Musaed bin Jalawi Street',
                    'district' => 'Al Murabba',
                    'city' => 'Riyadh',
                    'postal_code' => '12612',
                    'additional' => '3841',
                    'country' => 'SA',
                ],
            ],
        ],
    ];

    foreach ($cases as $case) {
        $invoice = Invoice::query()->create([
            'branch_id' => $this->branchId,
            'document_number' => $case['number'].'-'.uniqid(),
            'document_type' => $case['type'],
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
            'payload' => $case['payload'],
        ]);

        $sheet = PrintSheetFactory::invoice($invoice, Request::create('/', 'GET', ['template' => 'modern']), true);
        $mpdfHtml = view('pdf.invoice-mpdf', $sheet['data'])->render();

        expect($mpdfHtml)
            ->not->toContain('<?xml')
            ->not->toContain('<script');

        $binary = DocumentPdfRenderer::renderPrintSheet($sheet);

        expect($binary)->toStartWith('%PDF')
            ->and(stripos($binary, 'mPDF'))->not->toBeFalse()
            ->and(stripos($binary, 'dompdf'))->toBeFalse()
            ->and(DocumentPdfRenderer::pageCountInBinary($binary))->toBe(1);
    }

    Event::assertNotDispatched(MessageLogged::class, function (MessageLogged $event): bool {
        return $event->level === 'error' && $event->message === 'document_pdf_mpdf_failed';
    });
});