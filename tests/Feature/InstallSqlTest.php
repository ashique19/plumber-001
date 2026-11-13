<?php

use Tests\Support\SqlScriptStatements;

it('ships a mysql-oriented install.sql with seed data', function () {
    $path = base_path('docs/sql/install.sql');
    expect($path)->toBeReadableFile();

    $sql = file_get_contents($path);
    expect($sql)->toBeString()
        ->and($sql)->toContain('SET NAMES utf8mb4')
        ->and($sql)->toContain('ENGINE=InnoDB')
        ->and($sql)->toContain('INSERT IGNORE INTO business_settings')
        ->and($sql)->not->toMatch('/"migrations"/')
        ->and($sql)->not->toMatch('/autoincrement/i');

    $statements = SqlScriptStatements::split($sql);
    expect($statements)->not->toBeEmpty();

    $createTables = array_filter($statements, fn (string $stmt) => str_starts_with(strtoupper($stmt), 'CREATE TABLE'));
    expect(count($createTables))->toBeGreaterThan(5);
});
