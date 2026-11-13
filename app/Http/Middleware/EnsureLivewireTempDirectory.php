<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureLivewireTempDirectory
{
    public function handle(Request $request, Closure $next): Response
    {
        $disk = config('livewire.temporary_file_upload.disk') ?: config('filesystems.default', 'local');
        $directory = config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp';

        $root = match ($disk) {
            'local' => storage_path('app/'.$directory),
            default => storage_path('app/'.$directory),
        };

        try {
            File::ensureDirectoryExists($root);
            if (! is_writable($root)) {
                Log::error('livewire.upload.directory_not_writable', [
                    'path' => $root,
                    'disk' => $disk,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('livewire.upload.directory_create_failed', [
                'path' => $root,
                'message' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
