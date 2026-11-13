<?php

namespace App\Providers;

use App\Models\Branch;
use App\Models\PosOrder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $livewireTmp = storage_path('app/'.(config('livewire.temporary_file_upload.directory') ?: 'livewire-tmp'));
        File::ensureDirectoryExists($livewireTmp);

        Livewire::addPersistentMiddleware([
            \App\Http\Middleware\LoadBusinessSettings::class,
        ]);

        Route::bind('branch', fn (string $value) => Branch::query()->whereKey($value)->firstOrFail());
        Route::bind('order', fn (string $value) => PosOrder::query()->whereKey($value)->firstOrFail());
    }
}
