<?php

use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Database\Seeders\DemoStaffSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new DemoStaffSeeder)->run();
    \App\Business\BusinessContext::set(BusinessSetting::singleton());
    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
});

it('renders document create typography hooks for bn invoice issue', function () {
    $this->actingAs($this->owner, 'staff');

  foreach (['en', 'bn', 'ar'] as $locale) {
        $html = (string) $this->get(route('admin.invoices.issue', ['mode' => 'simplified', 'locale' => $locale]))->getContent();

        expect($html)
            ->toContain('document-readonly-field__label')
            ->toContain('invoice-document-lines')
            ->toContain('lang="'.$locale.'"');
    }
});

it('uses shorter bangla document number pending copy', function () {
    app()->setLocale('bn');

    expect(__('invoices.document_number_pending'))->toBe('সংরক্ষণের পর');
});