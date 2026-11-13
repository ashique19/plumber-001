<?php

namespace Tests\Support;

final class SqlScriptStatements
{
    /**
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $buffer = '';
        $statements = [];

        foreach ($lines as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '--')) {
                continue;
            }

            $buffer .= $line."\n";
            if (preg_match('/;\s*$/', rtrim($line))) {
                $stmt = trim($buffer);
                if ($stmt !== '' && $stmt !== ';') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
