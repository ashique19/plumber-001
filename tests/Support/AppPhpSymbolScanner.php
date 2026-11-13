<?php

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class AppPhpSymbolScanner
{
    /**
     * @return list<array{path: string, kind: string, fqcn: string}>
     */
    public static function symbols(): array
    {
        $symbols = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            $namespace = '';
            if (preg_match('/namespace\s+([^;]+);/', $contents, $namespaceMatch) === 1) {
                $namespace = trim($namespaceMatch[1]).'\\';
            }

            $pattern = '/\b(?:abstract\s+|final\s+)?(?<kind>class|interface|trait|enum)\s+(?<name>[A-Za-z_][A-Za-z0-9_]*)/';
            if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER) !== false) {
                foreach ($matches as $match) {
                    $symbols[] = [
                        'path' => $path,
                        'kind' => $match['kind'],
                        'fqcn' => $namespace.$match['name'],
                    ];
                }
            }
        }

        return $symbols;
    }
}
