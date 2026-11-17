<?php

it('uses ghost cancel and grouped document create footer actions', function () {
    $blade = file_get_contents(resource_path('views/components/documents/create-footer.blade.php'));

    expect($blade)
        ->toContain('tap-action-ghost')
        ->toContain('document-create-footer__primary')
        ->toContain('document-create-footer__secondary');
});

it('aligns document create footer with admin main content column', function () {
    $css = file_get_contents(resource_path('css/document-create-layout.css'));

    expect($css)
        ->toContain('app-main--with-sidebar .document-create-footer')
        ->toContain('inset-inline-start: 16rem')
        ->toContain('max-width: min(100%, 100rem)');
});

it('aligns typeahead add button height with search input', function () {
    $css = file_get_contents(resource_path('css/admin-forms-modern.css'));

    expect($css)->toContain('admin-typeahead__control--with-add');
});