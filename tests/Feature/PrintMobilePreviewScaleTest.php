<?php

it('scales fixed A4 width print sheets in the preview script', function () {
    $js = (string) file_get_contents(resource_path('js/invoice-print-preview.js'));

    expect($js)
        ->toContain('invoice-print-root.invoice-print-sheet')
        ->toContain('invoice-print-preview-scale')
        ->toContain('ResizeObserver')
        ->toContain('794');
});

it('defines screen preview host and print reset for A4 sheets in CSS', function () {
    $css = (string) file_get_contents(resource_path('css/invoice-print.css'));

    expect($css)
        ->toContain('invoice-print-preview-host')
        ->toContain('--inv-print-a4-width: 794px')
        ->toContain('.invoice-print-preview-scale')
        ->toContain('.invoice-print-root.invoice-print-sheet')
        ->toContain('transform: none !important');
});