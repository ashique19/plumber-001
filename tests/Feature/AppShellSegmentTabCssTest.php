<?php

it('ships compiled css for admin segment tabs with solid active fill', function () {
    $cssFiles = glob(public_path('build/assets/app-*.css'));

    expect($cssFiles)->not->toBeEmpty();

    $haystack = '';
    foreach ($cssFiles as $path) {
        $haystack .= (string) file_get_contents($path);
    }

    expect($haystack)
        ->toContain('.app-segment-tab--active')
        ->and($haystack)->toMatch('/\.app-segment-tab--active\{[^}]*background-color:[^}]*\}/')
        ->and($haystack)->toContain('admin-location-picker');
});
