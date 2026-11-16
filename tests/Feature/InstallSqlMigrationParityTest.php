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

it('documents install-schema-repair sql for live drift', function () {
    expect(base_path('docs/sql/2026-11-14-install-schema-repair.sql'))->toBeReadableFile();
});

it('documents invoice payments sql for live drift', function () {
    expect(base_path('docs/sql/2026-11-15-invoice-payments.sql'))->toBeReadableFile();
});

it('documents product units sizes sql for live drift', function () {
    expect(base_path('docs/sql/2026-11-16-product-units-sizes.sql'))->toBeReadableFile();
    $sql = file_get_contents(base_path('docs/sql/2026-11-16-product-units-sizes.sql'));
    expect($sql)->toContain('product_units')
        ->and($sql)->toContain('product_sizes')
        ->and($sql)->toContain('product_sku_counters');
});

it('documents combined plumber modules sql for live drift', function () {
    expect(base_path('docs/sql/2026-11-15-plumber-modules-combined.sql'))->toBeReadableFile();
    $combined = file_get_contents(base_path('docs/sql/2026-11-15-plumber-modules-combined.sql'));
    expect($combined)->toContain('quotations')
        ->and($combined)->toContain('business_expenses')
        ->and($combined)->toContain('invoice_payments');
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
