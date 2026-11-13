<?php

namespace App\Console\Commands;

use App\Support\Upload\LogoUploadConstraints;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class UploadDiagnosticsCommand extends Command
{
    protected $signature = 'app:upload-diagnostics';

    protected $description = 'Report PHP upload limits, Livewire temp storage, and logo paths for hosting troubleshooting';

    public function handle(): int
    {
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';
        $tempPath = storage_path('app/'.$directory);
        $publicImages = public_path('images');

        $this->table(['Check', 'Value'], [
            ['APP_URL', (string) config('app.url')],
            ['upload_max_filesize', (string) ini_get('upload_max_filesize')],
            ['post_max_size', (string) ini_get('post_max_size')],
            ['memory_limit', (string) ini_get('memory_limit')],
            ['max_file_uploads', (string) ini_get('max_file_uploads')],
            ['Livewire temp path', $tempPath],
            ['Livewire temp exists', is_dir($tempPath) ? 'yes' : 'no'],
            ['Livewire temp writable', is_writable($tempPath) ? 'yes' : (is_dir($tempPath) ? 'no' : 'n/a')],
            ['public/images writable', is_writable($publicImages) ? 'yes' : 'no'],
            ['Logo max (stored)', $this->formatBytes(LogoUploadConstraints::MAX_BYTES)],
            ['Logo temp max', LogoUploadConstraints::TEMP_MAX_KILOBYTES.' KB (Livewire)'],
        ]);

        if (! is_dir($tempPath)) {
            $this->warn('Creating Livewire temp directory…');
            File::ensureDirectoryExists($tempPath);
        }

        if (is_dir($tempPath) && ! is_writable($tempPath)) {
            $this->error('storage/app/'.$directory.' is not writable. On cPanel: set folder permissions to 755 or 775 and ensure the PHP user owns the directory.');

            return self::FAILURE;
        }

        $this->info('If uploads still fail behind HTTPS, set APP_URL to your public https URL and enable trusted proxies (see PR notes).');

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return round($bytes / 1_048_576, 2).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }
}
