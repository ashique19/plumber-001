<?php

namespace Tests\Support;

use PDO;

final class InstallSqlSqlRunner
{
    public static function adminPdo(bool $useRoot = false): PDO
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));

        if ($useRoot) {
            $user = (string) env('INSTALL_SQL_MYSQL_ROOT_USER', 'root');
            $password = (string) env('INSTALL_SQL_MYSQL_ROOT_PASSWORD', 'root');
        } else {
            $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
            $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        }

        return new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ],
        );
    }

    public static function databasePdo(?string $database = null): PDO
    {
        $host = (string) env('INSTALL_SQL_MYSQL_HOST', env('DB_HOST', '127.0.0.1'));
        $port = (int) env('INSTALL_SQL_MYSQL_PORT', env('DB_PORT', 3306));
        $user = (string) env('INSTALL_SQL_MYSQL_USER', env('DB_USERNAME', 'fancy'));
        $password = (string) env('INSTALL_SQL_MYSQL_PASSWORD', env('DB_PASSWORD', 'fancytest'));
        $database ??= InstallSqlMysqlTestEnvironment::databaseName();

        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ],
        );
    }

    public static function execStatements(PDO $pdo, string $sql): void
    {
        foreach (SqlScriptStatements::split($sql) as $statement) {
            if (trim($statement) === '') {
                continue;
            }
            $pdo->exec($statement);
            self::drainPendingResults($pdo);
        }
    }

    private static function drainPendingResults(PDO $pdo): void
    {
        try {
            $stmt = $pdo->query('DO 0');
            $stmt?->closeCursor();
        } catch (\PDOException) {
            // Connection is idle.
        }
    }

    public static function applyGlobPatches(string $pattern): void
    {
        $pdo = self::databasePdo();

        foreach (glob($pattern) as $path) {
            $sql = file_get_contents($path);
            if ($sql === false) {
                continue;
            }
            self::execStatements($pdo, $sql);
        }
    }
}