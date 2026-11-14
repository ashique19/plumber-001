<?php

namespace Tests\Support;

use PDO;

final class MysqlInformationSchema
{
    /**
     * @return array<string, array<string, string>> table => column => normalized COLUMN_TYPE
     */
    public static function columnsByTable(PDO $pdo, string $schema): array
    {
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ?
             ORDER BY TABLE_NAME, ORDINAL_POSITION',
        );
        $stmt->execute([$schema]);
        $tables = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $table = (string) $row['TABLE_NAME'];
            $column = (string) $row['COLUMN_NAME'];
            $tables[$table][$column] = self::normalizeColumnType(
                (string) $row['COLUMN_TYPE'],
                (string) $row['IS_NULLABLE'],
            );
        }

        return $tables;
    }

    /**
     * @param  array<string, array<string, string>>  $install
     * @param  array<string, array<string, string>>  $migrated
     * @param  list<string>  $tables
     * @return list<string>
     */
    public static function diffApplicationSchemas(
        array $install,
        array $migrated,
        array $tables,
    ): array {
        $mismatches = [];

        foreach ($tables as $table) {
            if (! isset($install[$table])) {
                $mismatches[] = "install.sql database missing table `{$table}`";

                continue;
            }
            if (! isset($migrated[$table])) {
                $mismatches[] = "migrated database missing table `{$table}`";

                continue;
            }

            foreach ($migrated[$table] as $column => $type) {
                if (! isset($install[$table][$column])) {
                    $mismatches[] = "`{$table}`.`{$column}` missing after install.sql import (migrated has {$type})";

                    continue;
                }

                if ($install[$table][$column] !== $type) {
                    $mismatches[] = "`{$table}`.`{$column}`: install.sql {$install[$table][$column]} vs migrated {$type}";
                }
            }

            foreach ($install[$table] as $column => $type) {
                if (! isset($migrated[$table][$column])) {
                    $mismatches[] = "`{$table}`.`{$column}` present after install.sql ({$type}) but missing in migrated schema";
                }
            }
        }

        return $mismatches;
    }

    public static function normalizeColumnType(string $columnType, string $isNullable): string
    {
        unset($isNullable);
        $type = strtolower(trim($columnType));
        $type = preg_replace('/\bbigint\(\d+\)\s+unsigned\b/', 'bigint unsigned', $type) ?? $type;
        $type = preg_replace('/\bint\(\d+\)\s+unsigned\b/', 'int unsigned', $type) ?? $type;
        $type = preg_replace('/\bsmallint\(\d+\)\s+unsigned\b/', 'smallint unsigned', $type) ?? $type;
        $type = preg_replace('/\btinyint\(\d+\)/', 'tinyint(1)', $type) ?? $type;
        $type = preg_replace('/\bdecimal\((\d+),(\d+)\)/', 'decimal($1,$2)', $type) ?? $type;
        $type = preg_replace('/\bvarchar\((\d+)\)\s*character set \w+/', 'varchar($1)', $type) ?? $type;
        $type = preg_replace('/\s+/', ' ', $type) ?? $type;

        return $type;
    }
}