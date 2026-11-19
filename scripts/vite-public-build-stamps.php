<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root.'/public/build/manifest.json';
$outPath = $root.'/public/build/vite-stamps.json';

if (! is_file($manifestPath)) {
    fwrite(STDERR, "manifest missing: {$manifestPath}\n");
    exit(1);
}

/** @var array<string, mixed> $manifest */
$manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

$stamps = [];
foreach ($manifest as $key => $meta) {
    if (! str_starts_with($key, 'resources/')) {
        continue;
    }
    if (! is_array($meta) || empty($meta['file'])) {
        continue;
    }
    $sourcePath = $root.'/'.$key;
    if (! is_file($sourcePath)) {
        continue;
    }
    $builtPath = $root.'/public/build/'.$meta['file'];
    if (! is_file($builtPath)) {
        fwrite(STDERR, "built asset missing for {$key}: {$builtPath}\n");
        exit(1);
    }
    $stamps[$key] = [
        'output' => $meta['file'],
        'sourceSha256' => hash_file('sha256', $sourcePath),
        'outputSha256' => hash_file('sha256', $builtPath),
    ];
}

ksort($stamps);
file_put_contents(
    $outPath,
    json_encode($stamps, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
);

echo "Wrote ".count($stamps)." stamp(s) to public/build/vite-stamps.json\n";