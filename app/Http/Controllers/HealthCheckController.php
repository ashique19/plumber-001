<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $livewireTmp = storage_path('app/'.(config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp'));

        $checks = [
            'app' => true,
            'database' => false,
            'uploads' => [
                'livewire_tmp_writable' => is_dir($livewireTmp) && is_writable($livewireTmp),
                'public_images_writable' => is_writable(public_path('images')),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        $ok = $checks['database'];
        $status = $ok ? 200 : 503;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $status);
    }
}
