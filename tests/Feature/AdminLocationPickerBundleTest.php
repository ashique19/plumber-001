<?php

it('ships admin location picker alpine component in the app bundle', function () {
    $jsFiles = glob(public_path('build/assets/app-*.js')) ?: [];
    expect($jsFiles)->not->toBeEmpty();

    $haystack = '';
    foreach ($jsFiles as $path) {
        $haystack .= (string) file_get_contents($path);
    }

    expect($haystack)->toContain('adminLocationPicker');
});

it('renders location picker x-data on business contact section', function () {
    (new Database\Seeders\DemoStaffSeeder)->run();
    $tenant = App\Models\BusinessSetting::query()->firstOrFail();
    $owner = App\Models\StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();

    $html = Livewire\Livewire::actingAs($owner, 'staff')
        ->test(App\Livewire\Admin\BusinessSettings::class)
        ->set('section', 'contact')
        ->html();

    expect($html)->toContain('adminLocationPicker');
});

it('scopes nominatim search to sa with abortable fetch in location picker js', function () {
    $source = file_get_contents(resource_path('js/admin-location-picker.js'));

    expect($source)
        ->toContain("countrycodes', 'sa'")
        ->toContain('AbortController');
});
