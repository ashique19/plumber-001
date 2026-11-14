<?php

namespace Tests\Support;

use PDO;

final class MigratedMysqlReferenceEnvironment
{
    private static bool $migrated = false;

    public static function referenceDatabaseName(): string
    {
        return InstallSqlMysqlTestEnvironment::databaseName().'_migrated';
    }

    public static function bootstrapFreshMigrate(): void
    {
        if (self::$migrated) {
            return;
        }

        if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
            return;
        }

        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        $database = self::referenceDatabaseName();

        $admin = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
        $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $command = sprintf(
            'cd %s && DB_CONNECTION=mysql DB_HOST=%s DB_PORT=%d DB_DATABASE=%s DB_USERNAME=%s DB_PASSWORD=%s php artisan migrate:fresh --force --no-interaction 2>&1',
            escapeshellarg(base_path()),
            escapeshellarg($host),
            $port,
            escapeshellarg($database),
            escapeshellarg($user),
            escapeshellarg($password),
        );

        $output = [];
        $exit = 0;
        exec($command, $output, $exit);

        if ($exit !== 0) {
            throw new \RuntimeException(
                'migrate:fresh on reference database failed (exit '.$exit.'): '.implode("\n", $output)
            );
        }

        self::$migrated = true;
    }

    public static function connection(): PDO
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));

        return new PDO(
            "mysql:host={$host};port={$port};dbname=".self::referenceDatabaseName().';charset=utf8mb4',
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    public static function installConnection(): PDO
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        $database = InstallSqlMysqlTestEnvironment::databaseName();

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}