<?php

use Tests\Support\InstallSqlTableParser;
use Tests\Support\MigrationMysqlSchemaManifest;

it('matches install.sql column types to the application migration manifest', function () {
    $path = base_path('docs/sql/install.sql');
    $sql = file_get_contents($path);
    expect($sql)->toBeString();

    $mismatches = InstallSqlTableParser::diffAgainstMigration($sql);

    expect($mismatches)->toBeEmpty(
        "install.sql schema mismatches:\n".implode("\n", $mismatches)
    );
});

it('does not define a legacy ingredients.name column in install.sql', function () {
    $parsed = InstallSqlTableParser::parseInstallSql(file_get_contents(base_path('docs/sql/install.sql')) ?: '');

    expect($parsed)->toHaveKey('ingredients')
        ->and($parsed['ingredients'])->not->toHaveKey('name');
});

it('lists every application table from the migration in install.sql', function () {
    $parsed = InstallSqlTableParser::parseInstallSql(file_get_contents(base_path('docs/sql/install.sql')) ?: '');
    $expected = MigrationMysqlSchemaManifest::applicationTables();

    foreach ($expected as $table) {
        expect(array_key_exists($table, $parsed))
            ->toBeTrue("install.sql is missing CREATE TABLE for `{$table}`");
    }
});
