<?php

namespace Tests\Support;

final class InstallSqlTableParser
{
    /**
     * @return array<string, array<string, string>> table => column => normalized type
     */
    public static function parseInstallSql(string $sql): array
    {
        $tables = [];

        if (! preg_match_all(
            '/CREATE TABLE IF NOT EXISTS `([^`]+)`\s*\(([\s\S]*?)\)\s*(?:ENGINE=InnoDB[^;]*)?\s*;/i',
            $sql,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        foreach ($matches as $match) {
            $table = $match[1];
            $body = $match[2];
            $columns = [];

            foreach (preg_split('/\n/', $body) as $line) {
                $line = trim($line);
                if ($line === '' || preg_match('/^(PRIMARY KEY|KEY |UNIQUE |CONSTRAINT |FOREIGN KEY)/i', $line)) {
                    continue;
                }

                if (preg_match('/^`([^`]+)`\s+(.+?)(?:,)?$/i', rtrim($line, ','), $m)) {
                    $columns[$m[1]] = self::normalizeType($m[2]);
                }
            }

            $tables[$table] = $columns;
        }

        return $tables;
    }

    public static function normalizeType(string $definition): string
    {
        $def = strtolower(trim($definition));
        $def = preg_replace('/\s+not\s+null.*$/', '', $def) ?? $def;
        $def = preg_replace('/\s+null.*$/', '', $def) ?? $def;
        $def = preg_replace('/\s+default\s+.*$/', '', $def) ?? $def;
        $def = preg_replace('/\s+auto_increment.*$/', '', $def) ?? $def;
        $def = preg_replace('/\s+primary\s+key.*$/', '', $def) ?? $def;
        $def = trim($def);

        if (preg_match('/^(bigint|int|smallint|tinyint)(\s+unsigned)?/', $def, $m)) {
            $base = $m[1];
            $unsigned = str_contains($def, 'unsigned') ? ' unsigned' : '';
            if ($base === 'tinyint' && ! str_contains($def, '(')) {
                return 'tinyint(1)';
            }

            return $base.$unsigned;
        }

        if (preg_match('/^varchar\((\d+)\)/', $def, $m)) {
            return 'varchar('.$m[1].')';
        }

        if (preg_match('/^decimal\((\d+),\s*(\d+)\)/', $def, $m)) {
            return 'decimal('.$m[1].','.$m[2].')';
        }

        if (str_starts_with($def, 'json')) {
            return 'json';
        }

        if (str_starts_with($def, 'longtext')) {
            return 'longtext';
        }

        if (str_starts_with($def, 'text')) {
            return 'text';
        }

        if (str_starts_with($def, 'datetime') || str_starts_with($def, 'timestamp')) {
            return 'datetime';
        }

        return preg_replace('/\s+/', ' ', $def) ?? $def;
    }

    /**
     * @return list<string>
     */
    public static function diffAgainstMigration(string $installSql): array
    {
        $expected = MigrationMysqlSchemaManifest::columnExpectations();
        $actual = self::parseInstallSql($installSql);
        $mismatches = [];

        foreach ($expected as $table => $columns) {
            if (! isset($actual[$table])) {
                $mismatches[] = "missing table `{$table}` in install.sql";

                continue;
            }

            foreach ($columns as $column => $meta) {
                if (! isset($actual[$table][$column])) {
                    $mismatches[] = "`{$table}`.`{$column}` missing in install.sql (expected {$meta['type']})";

                    continue;
                }

                if (! $meta['strict']) {
                    continue;
                }

                if ($actual[$table][$column] !== $meta['type']) {
                    $mismatches[] = "`{$table}`.`{$column}`: install.sql has {$actual[$table][$column]}, migration expects {$meta['type']}";
                }
            }
        }

        return $mismatches;
    }
}
