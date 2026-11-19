<?php

it('bn lang files do not place a bangla dependent mark directly after latin letters', function () {
    $pattern = '/[A-Za-z][\x{09BE}-\x{09C4}\x{09C7}-\x{09CB}\x{09CC}\x{09D7}\x{09CD}]/u';
    $violations = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('lang/bn'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace(base_path().'/', '', $file->getPathname());
        $lines = file($file->getPathname());

        foreach ($lines as $lineNumber => $line) {
            if (preg_match($pattern, $line, $match, PREG_OFFSET_CAPTURE)) {
                $violations[] = sprintf('%s:%d contains %s', $relative, $lineNumber + 1, $match[0][0]);
            }
        }
    }

    expect($violations)->toBeEmpty(implode("\n", $violations));
});