<?php

it('committed vite bundles for invoice print include modern polish rules', function () {
    $manifestPath = public_path('build/manifest.json');
    expect(is_file($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $expectations = [
        'resources/css/invoice-print.css' => [
            '.invoice-pdf-ar-preline',
            'white-space:nowrap',
            'invoice-a4__buyer-address-en__line',
        ],
        'resources/css/print-template-modern.css' => [
            'min-width:200px',
            'invoice-a4__qr-note-ar',
            'white-space:nowrap',
        ],
    ];

    foreach ($expectations as $entry => $needles) {
        expect($manifest)->toHaveKey($entry);
        $rel = $manifest[$entry]['file'] ?? null;
        expect($rel)->not->toBeEmpty();
        $builtPath = public_path('build/'.$rel);
        expect(is_file($builtPath))->toBeTrue();
        $css = file_get_contents($builtPath);
        foreach ($needles as $needle) {
            expect($css)->toContain($needle, "Expected {$entry} bundle [{$rel}] to contain {$needle}");
        }
    }
});