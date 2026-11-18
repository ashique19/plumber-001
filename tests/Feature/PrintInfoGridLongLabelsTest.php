<?php

use App\Support\Invoicing\DocumentPdfRenderer;
use App\Support\Invoicing\InvoicePrintInfoGrid;
use Illuminate\Support\Facades\Blade;

it('renders long meta labels in separate grid cells without nowrap on label columns', function () {
    $html = Blade::render(
        '<x-invoices.print-info-grid :rows="$rows" />',
        ['rows' => InvoicePrintInfoGrid::longLabelFixtureRows()],
    );

    expect($html)
        ->toContain('width:'.InvoicePrintInfoGrid::COL_EN_PCT.'%', false)
        ->toContain('Quotation number', false)
        ->toContain('HARNESS-QUO-LABEL-001', false)
        ->toContain('Payment terms', false)
        ->toContain('Net 30 days', false)
        ->toMatch('/<td[^>]*invoice-print-info-grid__en[^>]*>[\s\S]*?Quotation number[\s\S]*?<\/td>/')
        ->toMatch('/<td[^>]*invoice-print-info-grid__value[^>]*>[\s\S]*?HARNESS-QUO-LABEL-001[\s\S]*?<\/td>/')
        ->not->toMatch('/Quotation numberHARNESS/');

    $wrapped = DocumentPdfRenderer::wrapHtml($html);

    expect($wrapped)
        ->toContain('invoice-print-info-grid__en')
        ->toContain('white-space:normal!important')
        ->toContain('width:30%!important')
        ->toContain('width:42%!important')
        ->not->toContain('invoice-print-info-grid__en{text-align:left!important;direction:ltr!important;unicode-bidi:embed!important;font-weight:normal!important;font-size:8pt!important;font-family:dejavusans!important;color:#666!important;width:20%!important');
});