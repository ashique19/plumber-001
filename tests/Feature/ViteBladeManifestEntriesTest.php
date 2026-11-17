<?php

it('lists every @vite blade entry in the build manifest', function () {
    $manifestPath = public_path('build/manifest.json');

    expect(is_file($manifestPath))->toBeTrue();

    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

    $paths = collect();

    foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
        $source = (string) file_get_contents($file);

        if (! preg_match_all("/@vite\\(\\[(.*?)\\]\\)/s", $source, $matches)) {
            continue;
        }

        foreach ($matches[1] as $group) {
            if (! preg_match_all("/'((?:resources|public)\\/[^']+)'/", $group, $entries)) {
                continue;
            }

            foreach ($entries[1] as $entry) {
                $paths->push($entry);
            }
        }
    }

    $paths = $paths->unique()->sort()->values();

    expect($paths->isNotEmpty())->toBeTrue();

    foreach ($paths as $entry) {
        expect(isset($manifest[$entry]))->toBeTrue("Missing Vite manifest entry for {$entry}");
    }
});