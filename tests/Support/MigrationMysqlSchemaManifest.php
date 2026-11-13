<?php

namespace Tests\Support;

/**
 * Expected MySQL column types derived from application migrations (single_client_application_schema).
 */
final class MigrationMysqlSchemaManifest
{
    /**
     * @return list<string>
     */
    public static function applicationTables(): array
    {
        return array_keys(self::columnExpectations());
    }

    /**
     * @return array<string, array<string, array{type: string, strict: bool}>>
     */
    public static function columnExpectations(): array
    {
        return self::parseMigration();
    }

    /**
     * @return array<string, array<string, string>> table => column => normalized type (strict columns only)
     */
    public static function strictColumnTypes(): array
    {
        $out = [];
        foreach (self::columnExpectations() as $table => $columns) {
            foreach ($columns as $column => $meta) {
                if ($meta['strict']) {
                    $out[$table][$column] = $meta['type'];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string, array<string, array{type: string, strict: bool}>>
     */
    private static function parseMigration(): array
    {
        $path = base_path('database/migrations/0001_01_01_000010_single_client_application_schema.php');
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Unable to read migration: {$path}");
        }

        if (! preg_match_all(
            "/Schema::create\('([^']+)',\s*function\s*\(Blueprint\s*\\\$table\)\s*\{([\s\S]*?)\n\s*\}\);/",
            $content,
            $blocks,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        $tables = [];

        foreach ($blocks as $block) {
            $table = $block[1];
            $body = $block[2];
            $columns = [];

            if (preg_match('/\$table->id\(\)/', $body)) {
                $columns['id'] = ['type' => 'bigint unsigned', 'strict' => true];
            }

            foreach (preg_split('/\n/', $body) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '$table->index')
                    || str_starts_with($line, '$table->unique') || str_starts_with($line, '$table->primary')
                    || str_starts_with($line, '$table->foreign')) {
                    continue;
                }

                if (preg_match('/\$table->timestamps\(\)/', $line)) {
                    $columns['created_at'] = ['type' => 'datetime', 'strict' => false];
                    $columns['updated_at'] = ['type' => 'datetime', 'strict' => false];

                    continue;
                }

                if (preg_match('/\$table->rememberToken\(\)/', $line)) {
                    $columns['remember_token'] = ['type' => 'varchar(100)', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->string\('([^']+)'(?:,\s*(\d+))?\)/", $line, $m)) {
                    $len = isset($m[2]) ? (int) $m[2] : 255;
                    $columns[$m[1]] = [
                        'type' => "varchar({$len})",
                        'strict' => isset($m[2]),
                    ];

                    continue;
                }

                if (preg_match("/\\\$table->text\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'text', 'strict' => false];

                    continue;
                }

                if (preg_match("/\\\$table->longText\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'longtext', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->decimal\('([^']+)',\s*(\d+),\s*(\d+)\)/", $line, $m)) {
                    $columns[$m[1]] = [
                        'type' => 'decimal('.((int) $m[2]).','.((int) $m[3]).')',
                        'strict' => true,
                    ];

                    continue;
                }

                if (preg_match("/\\\$table->json\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'json', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->boolean\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'tinyint(1)', 'strict' => false];

                    continue;
                }

                if (preg_match("/\\\$table->integer\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'int', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->unsignedBigInteger\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'bigint unsigned', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->unsignedSmallInteger\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'smallint unsigned', 'strict' => true];

                    continue;
                }

                if (preg_match("/\\\$table->timestamp\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'datetime', 'strict' => false];

                    continue;
                }

                if (preg_match("/\\\$table->foreignId\('([^']+)'\)/", $line, $m)) {
                    $columns[$m[1]] = ['type' => 'bigint unsigned', 'strict' => false];
                }
            }

            $tables[$table] = $columns;
        }

        return $tables;
    }
}
