<?php

namespace App\Support\Invoicing;

use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class DocumentPdfFonts
{
    public const FAMILY_LATIN = 'DejaVu Sans';

    public const FAMILY_ARABIC = 'AmiriPdf';

    public static function storageDir(): string
    {
        return storage_path('fonts');
    }

    public static function cacheDir(): string
    {
        return self::storageDir().'/cache';
    }

    /**
     * Called on app boot: cheap presence check only; install only when files are missing.
     */
    public static function warmAtBoot(): void
    {
        if (self::fontsPresent()) {
            return;
        }

        try {
            self::installIfNeeded();
        } catch (Throwable $e) {
            Log::warning('document_pdf_fonts_boot_install_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function ensureInstalled(): void
    {
        if (self::fontsPresent()) {
            return;
        }

        self::installIfNeeded();
    }

    public static function assertReady(): void
    {
        self::ensureInstalled();

        foreach (['Amiri-Regular.ttf', 'Amiri-Bold.ttf'] as $file) {
            if (! is_file(self::storageDir().'/'.$file)) {
                throw new RuntimeException('Document PDF font missing: '.$file);
            }
        }

        $vendorDejaVu = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (! is_file($vendorDejaVu) && ! is_file(self::storageDir().'/DejaVuSans.ttf')) {
            throw new RuntimeException('Bundled Dompdf DejaVuSans font missing.');
        }

        if (! is_dir(self::cacheDir())) {
            throw new RuntimeException('Document PDF font cache is missing: '.self::cacheDir());
        }

        if (! is_writable(self::cacheDir())) {
            throw new RuntimeException('Document PDF font cache is not writable: '.self::cacheDir());
        }
    }

    public static function applyTo(Options $options): void
    {
        self::ensureInstalled();
        $options->set('fontDir', self::storageDir());
        $options->set('fontCache', self::cacheDir());
    }

    public static function fontsPresent(): bool
    {
        $dir = self::storageDir();

        foreach (['Amiri-Regular.ttf', 'Amiri-Bold.ttf', 'DejaVuSans.ttf', 'installed-fonts.json'] as $file) {
            if (! is_file($dir.'/'.$file)) {
                return false;
            }
        }

        return is_dir(self::cacheDir());
    }

    private static function installIfNeeded(): void
    {
        try {
            File::ensureDirectoryExists(self::storageDir());
            File::ensureDirectoryExists(self::cacheDir());
        } catch (Throwable $e) {
            Log::warning('document_pdf_fonts_directory_failed', ['message' => $e->getMessage()]);

            return;
        }

        self::copyIfMissing(
            self::storageDir().'/Amiri-Regular.ttf',
            resource_path('fonts/Amiri-Regular.ttf'),
        );
        self::copyIfMissing(
            self::storageDir().'/Amiri-Bold.ttf',
            resource_path('fonts/Amiri-Bold.ttf'),
        );

        $vendorFonts = base_path('vendor/dompdf/dompdf/lib/fonts');
        foreach (glob($vendorFonts.'/DejaVuSans*') ?: [] as $source) {
            if (! is_file($source)) {
                continue;
            }
            $base = basename($source);
            if (preg_match('/\.(ttf|ufm|ufm\.json)$/', $base) !== 1) {
                continue;
            }
            self::copyIfMissing(self::storageDir().'/'.$base, $source);
        }

        self::writeInstalledFontsManifest();
    }

    private static function copyIfMissing(string $target, string $source): void
    {
        if (is_file($target) || ! is_file($source)) {
            return;
        }

        try {
            File::copy($source, $target);
        } catch (Throwable $e) {
            Log::warning('document_pdf_fonts_copy_failed', [
                'target' => $target,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private static function writeInstalledFontsManifest(): void
    {
        $manifest = [
            'dejavu sans' => [
                'normal' => 'DejaVuSans',
                'bold' => 'DejaVuSans-Bold',
                'italic' => 'DejaVuSans-Oblique',
                'bold_italic' => 'DejaVuSans-BoldOblique',
            ],
            'amiripdf' => [
                'normal' => 'Amiri-Regular',
                'bold' => 'Amiri-Bold',
                'italic' => 'Amiri-Regular',
                'bold_italic' => 'Amiri-Bold',
            ],
        ];

        $path = self::storageDir().'/installed-fonts.json';
        $encoded = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)) {
            return;
        }

        if (is_file($path) && @file_get_contents($path) === $encoded) {
            return;
        }

        try {
            file_put_contents($path, $encoded);
        } catch (Throwable $e) {
            Log::warning('document_pdf_fonts_manifest_write_failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }
}