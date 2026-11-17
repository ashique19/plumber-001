<?php

it('prevents repeating scroll fade gradients on hub document tables', function () {
    $css = (string) file_get_contents(resource_path('css/hub-document-table.css'));

    expect($css)
        ->toContain('.hub-doc-table-scroll')
        ->not->toContain('background-attachment: local')
        ->not->toContain('linear-gradient(to right')
        ->toContain('.hub-doc-table tbody td')
        ->toContain('background: var(--app-surface');
});

it('keeps document numbers nowrap and status pills on one line', function () {
    $css = (string) file_get_contents(resource_path('css/hub-document-table.css'));

    expect($css)
        ->toContain('.hub-doc-table__doc-number')
        ->toContain('white-space: nowrap')
        ->toContain('.hub-invoice-status-pill')
        ->toContain('white-space: nowrap');
});

it('uses short awaiting clearance labels in invoice hub', function () {
    $html = (string) file_get_contents(resource_path('views/livewire/invoices/invoice-hub.blade.php'));

    expect($html)
        ->toContain('hub-doc-table__doc-number')
        ->toContain('hub-doc-table__customer')
        ->toContain('dir="auto"')
        ->toContain('hub-doc-table__status-col');
});

it('renders quotation filters on one row at large breakpoints', function () {
    $html = (string) file_get_contents(resource_path('views/livewire/quotations/quotation-hub.blade.php'));

    expect($html)
        ->toContain('lg:grid-cols-3')
        ->toContain('lg:col-span-1');
});