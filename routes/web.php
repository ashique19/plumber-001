<?php

use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Middleware\RedirectIfBusinessGuest;
use Illuminate\Support\Facades\Route;

Route::get('/healthz', HealthCheckController::class)
    ->middleware('throttle:60,1')
    ->name('healthz');

Route::middleware(RedirectIfBusinessGuest::class)->group(function (): void {
    Route::get('/', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

require __DIR__.'/admin.php';

Route::post('/telemetry/livewire-client-failure', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::warning('livewire.client_failure', [
        'status' => $request->integer('status'),
        'snippet' => \Illuminate\Support\Str::limit((string) $request->input('snippet'), 500),
        'url' => (string) $request->input('url'),
        'staff_user_id' => auth('staff')->id(),
    ]);

    return response()->noContent();
})->middleware('web');
