<?php

use App\Livewire\Concerns\ValidatesFieldsLive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Livewire components whose views include <x-form with default live validation must
 * implement validateLiveField (via ValidatesFieldsLive or an explicit method).
 */
it('ensures livewire components with x-form live validation expose validateLiveField', function () {
    $viewRoot = resource_path('views/livewire');
    $missing = [];

    foreach (File::allFiles($viewRoot) as $file) {
        $relative = Str::after($file->getPathname(), $viewRoot.DIRECTORY_SEPARATOR);
        $blade = $file->getContents();
        if (! str_contains($blade, '<x-form.')) {
            continue;
        }
        $needsLive = str_contains($blade, ':liveValidate="true"')
            || (! str_contains($blade, ':liveValidate="false"') && preg_match('/<x-form\.(input|select|textarea)\b/', $blade));
        if (! $needsLive) {
            continue;
        }

        $class = 'App\\Livewire\\'.str_replace(['/', '.blade.php'], ['\\', ''], $relative);
        if (! class_exists($class)) {
            continue;
        }

        $usesTrait = in_array(ValidatesFieldsLive::class, class_uses_recursive($class), true);
        $hasMethod = method_exists($class, 'validateLiveField');
        if (! $usesTrait && ! $hasMethod) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});

it('business settings validateLiveField accepts blur on contact email', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = posTenantSetup();
    \App\Business\BusinessContext::set($tenant);
    $cashier->syncRoles(['owner']);

    Livewire::actingAs($cashier, 'staff')
        ->test(\App\Livewire\Admin\BusinessSettings::class)
        ->set('section', 'contact')
        ->call('validateLiveField', 'email')
        ->assertHasNoErrors();
});
