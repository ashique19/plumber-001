<?php

it('widens admin content on large breakpoints without changing the mobile shell cap', function () {
    $adminWidth = (string) file_get_contents(resource_path('css/admin-content-width.css'));
    $shell = (string) file_get_contents(resource_path('css/app-shell.css'));
    $create = (string) file_get_contents(resource_path('css/document-create-layout.css'));

    expect($adminWidth)
        ->toContain('min-width: 1024px')
        ->toContain('100rem')
        ->toContain('padding-inline: 1.5rem')
        ->toContain('min-width: 1280px')
        ->toContain('padding-inline: 2rem');

    expect($shell)->toContain('max-w-6xl');

    expect($create)
        ->toContain('--document-sidebar-width: 24rem')
        ->toContain('position: sticky')
        ->toMatch('/min-width: 1280px[\s\S]*position: sticky/');
});