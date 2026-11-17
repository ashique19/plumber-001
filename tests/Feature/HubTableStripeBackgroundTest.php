<?php

it('prevents repeating scroll fade gradients on hub document tables', function () {
    $css = (string) file_get_contents(resource_path('css/hub-document-table.css'));

    expect($css)
        ->toContain('.hub-doc-table-scroll')
        ->toContain('background-repeat: no-repeat')
        ->toContain('.hub-doc-table tbody td')
        ->toContain('background: var(--app-surface');
});

it('renders quotation filters on one row at large breakpoints', function () {
    $html = (string) file_get_contents(resource_path('views/livewire/quotations/quotation-hub.blade.php'));

    expect($html)
        ->toContain('lg:grid-cols-3')
        ->toContain('lg:col-span-1');
});