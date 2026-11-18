<?php

use Tests\Support\PdfTestArtifacts;

it('resolves a writable pdf artifact directory or null', function () {
    $dir = PdfTestArtifacts::directory();

    if ($dir === null) {
        expect($dir)->toBeNull();
    } else {
        expect(is_dir($dir))->toBeTrue()
            ->and(is_writable($dir))->toBeTrue();
    }
});