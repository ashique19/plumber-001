<?php

use Tests\Support\InstallSqlMysqlTestEnvironment;
use Tests\Support\MigratedMysqlReferenceEnvironment;
use Tests\Support\MigrationMysqlSchemaManifest;
use Tests\Support\MysqlInformationSchema;

it('matches a live install.sql import column-for-column with migrate:fresh on mysql', function () {
    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; install.sql schema parity requires MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    MigratedMysqlReferenceEnvironment::bootstrapFreshMigrate();

    $installDb = InstallSqlMysqlTestEnvironment::databaseName();
    $migratedDb = MigratedMysqlReferenceEnvironment::referenceDatabaseName();

    $install = MysqlInformationSchema::columnsByTable(
        MigratedMysqlReferenceEnvironment::installConnection(),
        $installDb,
    );
    $migrated = MysqlInformationSchema::columnsByTable(
        MigratedMysqlReferenceEnvironment::connection(),
        $migratedDb,
    );

    $tables = MigrationMysqlSchemaManifest::applicationTables();
    $mismatches = MysqlInformationSchema::diffApplicationSchemas($install, $migrated, $tables);

    expect($mismatches)->toBeEmpty(
        "install.sql imported schema drift vs migrate:fresh:\n".implode("\n", $mismatches)
    );
});