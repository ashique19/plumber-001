<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    BusinessSetting::singleton();
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('translates my profile in the admin sidebar for bn and ar', function () {
    foreach (['bn', 'ar'] as $locale) {
        app()->setLocale($locale);

        $html = $this->actingAs($this->owner, 'staff')
            ->get(route('admin.dashboard', ['locale' => $locale]))
            ->assertOk()
            ->getContent();

        expect($html)
            ->toContain(__('admin_nav.items.profile', [], $locale))
            ->not->toContain('My profile');
    }
});
