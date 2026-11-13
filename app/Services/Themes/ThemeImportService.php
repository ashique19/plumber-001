<?php

namespace App\Services\Themes;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\File;
use ZipArchive;

class ThemeImportService
{
    public function importFromZip(string $zipPath): BusinessSetting
    {
        $work = storage_path('app/theme-imports/'.uniqid('bundle-', true));
        File::ensureDirectoryExists($work);

        try {
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new \InvalidArgumentException('Invalid theme bundle zip.');
            }
            $zip->extractTo($work);
            $zip->close();

            $tenantFile = $work.'/tenant.json';
            if (! is_file($tenantFile)) {
                throw new \InvalidArgumentException('Bundle missing tenant.json');
            }

            /** @var array{name: string, settings: array<string, mixed>} $payload */
            $payload = json_decode(file_get_contents($tenantFile), true, flags: JSON_THROW_ON_ERROR);

            $assetsDir = $work.'/assets';
            if (is_dir($assetsDir)) {
                $this->copyAssetsIntoPublic($assetsDir, public_path());
            }

            $business = BusinessSetting::query()->find(1) ?? new BusinessSetting(['id' => 1]);
            $business->forceFill([
                'name' => $payload['name'] ?? $business->name ?? 'Business',
                'settings' => $payload['settings'] ?? $business->settings,
            ])->save();

            return $business;
        } finally {
            File::deleteDirectory($work);
        }
    }

    private function copyAssetsIntoPublic(string $from, string $toPublic): void
    {
        foreach (File::allFiles($from) as $file) {
            $relative = $file->getRelativePathname();
            $dest = $toPublic.'/'.str_replace('\\', '/', $relative);
            File::ensureDirectoryExists(dirname($dest));
            File::copy($file->getPathname(), $dest);
        }
    }
}
