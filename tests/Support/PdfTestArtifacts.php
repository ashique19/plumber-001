<?php

namespace Tests\Support;

final class PdfTestArtifacts
{
    /**
     * Writable directory for optional PDF/PNG test output, or null when skipped.
     */
    public static function directory(): ?string
    {
        foreach (self::candidates() as $dir) {
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
            if (@mkdir($dir, 0755, true) && is_writable($dir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function candidates(): array
    {
        return [
            '/opt/cursor/artifacts',
            storage_path('framework/testing/pdf-artifacts'),
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).'/plumber-pdf-artifacts',
        ];
    }
}