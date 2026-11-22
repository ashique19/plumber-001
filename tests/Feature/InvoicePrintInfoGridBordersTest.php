<?php

use App\Support\Invoicing\InvoicePrintInfoGrid;
use Illuminate\Support\Facades\Blade;

it('renders the meta block as one collapsed grid without nested table borders', function () {
    $html = Blade::render(
        '<x-invoices.print-info-grid :rows="$rows" />',
        ['rows' => array_merge(InvoicePrintInfoGrid::longLabelFixtureRows(), [
            ['en' => 'Due date', 'ar' => 'تاريخ الاستحقاق', 'value' => '2026-11-25', 'nowrap' => true, 'label_nowrap' => true],
        ])],
    );

    expect($html)
        ->toContain('invoice-print-info-grid')
        ->toContain('invoice-print-info-grid__empty')
        ->toContain('colspan="3"')
        ->not->toContain('invoice-print-info-grid-field')
        ->not->toContain('invoice-print-info-grid__pair')
        ->not->toContain('invoice-print-info-grid__spacer')
        ->toContain('Quotation number')
        ->toContain('Supply date');

    $css = file_get_contents(resource_path('css/invoice-print.css')) ?: '';

    expect($css)
        ->toContain('.invoice-print-info-grid,')
        ->toContain('border-collapse: collapse')
        ->toContain('border: 0.5pt solid var(--inv-border)')
        ->not->toMatch('/\.invoice-print-info-grid-field td\s*\{[^}]*border:/');
});
