<?php

use Tests\Support\AppPhpSymbolScanner;

it('autoloads every class, interface, trait, and enum under app/', function () {
    $symbols = AppPhpSymbolScanner::symbols();

    expect($symbols)->not->toBeEmpty();

    foreach ($symbols as $symbol) {
        $fqcn = $symbol['fqcn'];
        $path = $symbol['path'];
        $kind = $symbol['kind'];

        $loaded = match ($kind) {
            'interface' => interface_exists($fqcn),
            'trait' => trait_exists($fqcn),
            default => class_exists($fqcn),
        };

        expect($loaded)->toBeTrue("Failed to autoload {$kind} {$fqcn} ({$path})");
    }
});
