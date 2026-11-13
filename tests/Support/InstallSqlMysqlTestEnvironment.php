<?php

namespace Tests\Support;

use Database\Seeders\OwnerAdminSeeder;
use Database\Seeders\PlumberCatalogSeeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PDO;

final class InstallSqlMysqlTestEnvironment
{
    private static bool $bootstrapped = false;

    public static function mysqlReachable(): bool
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->query('SELECT 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function databaseName(): string
    {
        return (string) env('INSTALL_SQL_MYSQL_DATABASE', 'plumber_install_sql_smoke');
    }

    public static function bootstrapApplicationConnection(): void
    {
        if (self::$bootstrapped) {
            self::applyLaravelMysqlConfig();

            return;
        }

        if (! self::mysqlReachable()) {
            return;
        }

        InstallSqlRouteFixtures::reset();
        self::importInstallSql();
        self::applyLaravelMysqlConfig();
        (new OwnerAdminSeeder)->run();
        (new PlumberCatalogSeeder)->run();
        InstallSqlRouteFixtures::ensurePaidPosOrder();
        self::$bootstrapped = true;
    }

    public static function isReady(): bool
    {
        return self::$bootstrapped && self::mysqlReachable();
    }

    private static function applyLaravelMysqlConfig(): void
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (string) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', '3306'));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        $database = self::databaseName();

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $user,
            'password' => $password,
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private static function importInstallSql(): void
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        $database = self::databaseName();

        $admin = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $admin->exec("DROP DATABASE IF EXISTS `{$database}`");
        $admin->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $sql = file_get_contents(base_path('docs/sql/install.sql'));
        if ($sql === false) {
            throw new \RuntimeException('Unable to read docs/sql/install.sql');
        }

        foreach (SqlScriptStatements::split($sql) as $statement) {
            $pdo->exec($statement);
        }
    }
}
