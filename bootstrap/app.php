<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\LoadBusinessSettings::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'business.guest' => \App\Http\Middleware\RedirectIfBusinessGuest::class,
            'pos.access' => \App\Http\Middleware\EnsurePosAccess::class,
            'reports.access' => \App\Http\Middleware\EnsureReportsAccess::class,
            'inventory.access' => \App\Http\Middleware\EnsureInventoryAccess::class,
            'team.access' => \App\Http\Middleware\EnsureStaffManageAccess::class,
            'invoices.access' => \App\Http\Middleware\EnsureInvoicesAccess::class,
            'business.manage' => \App\Http\Middleware\EnsureBusinessManageAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->reportable(function (\Illuminate\Validation\ValidationException $e): void {
            if (! request()->is('livewire/upload-file')) {
                return;
            }

            \Illuminate\Support\Facades\Log::warning('livewire.upload.validation_failed', [
                'errors' => $e->errors(),
                'app_url' => config('app.url'),
                'content_length' => request()->header('Content-Length'),
            ]);
        });
    })->create();
