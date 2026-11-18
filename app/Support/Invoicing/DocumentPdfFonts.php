<?php

namespace App\Support\Invoicing;

use Dompdf\Options;
use Illuminate\Support\Facades\File;
use RuntimeException;

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

    public static function ensureInstalled(): void
    {
        File::ensureDirectoryExists(self::storageDir());
        File::ensureDirectoryExists(self::cacheDir());

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

    public static function assertReady(): void
    {
        self::ensureInstalled();

        foreach (['Amiri-Regular.ttf', 'Amiri-Bold.ttf'] as $file) {
            if (! is_file(self::storageDir().'/'.$file)) {
                throw new RuntimeException('Document PDF font missing: '.$file);
            }
        }

        $vendorDejaVu = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        if (! is_file($vendorDejaVu)) {
            throw new RuntimeException('Bundled Dompdf DejaVuSans font missing.');
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

    private static function copyIfMissing(string $target, string $source): void
    {
        if (is_file($target) || ! is_file($source)) {
            return;
        }

        File::copy($source, $target);
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

        if (! is_file($path) || file_get_contents($path) !== $encoded) {
            file_put_contents($path, $encoded);
        }
    }
}