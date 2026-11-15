<?php

use App\Livewire\Admin\BusinessSettings;
use Database\Seeders\DemoStaffSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    $this->owner = \App\Models\StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders scrollable section tabs and separated panels on business settings', function () {
    foreach (['en', 'bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $html = Livewire::actingAs($this->owner, 'staff')
            ->test(BusinessSettings::class)
            ->set('section', 'brand')
            ->html();

        expect($html)->toContain('app-section-tabs--scroll')
            ->and($html)->toContain('business-settings-page__panels')
            ->and($html)->toContain(__('business.brand.display_name_en', locale: $locale));
    }
});

it('exposes brand tab labels in bangla without duplicating mobile-only select', function () {
    app()->setLocale('bn');

    $html = Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'brand')
        ->html();

    expect($html)->toContain(__('business.sections.brand', locale: 'bn'))
        ->and($html)->toContain(__('business.brand.display_name_en', locale: 'bn'))
        ->and($html)->not->toContain('app-toolbar-select');
});