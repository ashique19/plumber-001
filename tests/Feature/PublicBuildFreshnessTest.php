<?php

it('committed public/build vite stamps match current resources and outputs', function () {
    $stampPath = public_path('build/vite-stamps.json');
    expect(is_file($stampPath))->toBeTrue();

    /** @var array<string, array{output: string, sourceSha256: string, outputSha256: string}> $stamps */
    $stamps = json_decode((string) file_get_contents($stampPath), true, 512, JSON_THROW_ON_ERROR);
    expect($stamps)->not->toBeEmpty();

    $manifestPath = public_path('build/manifest.json');
    /** @var array<string, array{file?: string}> $manifest */
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    foreach ($stamps as $entry => $stamp) {
        expect($manifest)->toHaveKey($entry);
        expect($manifest[$entry]['file'] ?? null)->toBe($stamp['output']);

        $sourcePath = base_path($entry);
        expect(is_file($sourcePath))->toBeTrue();
        expect(hash_file('sha256', $sourcePath))->toBe($stamp['sourceSha256']);

        $builtPath = public_path('build/'.$stamp['output']);
        expect(is_file($builtPath))->toBeTrue();
        expect(hash_file('sha256', $builtPath))->toBe($stamp['outputSha256']);
    }
});

it('committed vite bundles for invoice print include modern polish rules', function () {
    $manifestPath = public_path('build/manifest.json');
    expect(is_file($manifestPath))->toBeTrue();

    $manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $expectations = [
        'resources/css/invoice-print.css' => [
            'invoice-pdf-ar-preline',
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
            expect($css)->toContain($needle);
        }
    }
});