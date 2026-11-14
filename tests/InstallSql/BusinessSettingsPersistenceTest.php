<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Illuminate\Support\Facades\Config;
use Tests\Support\InstallSqlMysqlTestEnvironment;
use Tests\Support\InstallSqlSqlRunner;

afterEach(function () {
    Config::set('database.default', 'sqlite');
    DB::purge('mysql');
    DB::purge('sqlite');
});

beforeEach(function () {
    if (! InstallSqlMysqlTestEnvironment::mysqlReachable()) {
        $this->markTestSkipped('MySQL is not reachable; business settings persistence requires MySQL 8.');
    }

    InstallSqlMysqlTestEnvironment::bootstrapApplicationConnection();

    if (! InstallSqlMysqlTestEnvironment::isReady()) {
        $this->markTestSkipped('install.sql MySQL bootstrap failed.');
    }

    InstallSqlSqlRunner::applyGlobPatches(base_path('docs/sql/2026-11-14-*.sql'));

    $this->owner = StaffUser::query()->where('email', 'owner@example.test')->firstOrFail();
    $this->business = BusinessSetting::primary();
});

it('persists brand and legal tabs after install.sql import and sql patches on mysql', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('displayNameEn', 'Pipe Works KSA')
        ->set('displayNameAr', 'أنابيب السعودية')
        ->set('displayNameBn', 'পাইপ ওয়ার্ক্স কেএসএ')
        ->set('taglineAr', 'সঠিক নলকাজ')
        ->set('brandColorKey', 'steel')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $brand = BusinessSetting::primary()->fresh();
    expect(data_get($brand->settings, 'business.brand.display_name_en'))->toBe('Pipe Works KSA')
        ->and(data_get($brand->settings, 'business.brand.display_name_ar'))->toBe('أنابيب السعودية')
        ->and(data_get($brand->settings, 'business.brand.display_name_bn'))->toBe('পাইপ ওয়ার্ক্স কেএসএ')
        ->and(data_get($brand->settings, 'business.brand.brand_color_key'))->toBe('steel')
        ->and($brand->name)->toBe('Pipe Works KSA');

    $reloadedBrand = Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->assertSet('displayNameAr', 'أنابيب السعودية')
        ->assertSet('displayNameBn', 'পাইপ ওয়ার্ক্স কেএসএ');

    expect($reloadedBrand)->not->toBeNull();

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'legal')
        ->set('legalNameEn', 'Pipe Works LLC')
        ->set('legalNameAr', 'شركة أنابيب')
        ->set('crNumber', '1011223344')
        ->set('vatNumber', '311122393500003')
        ->set('legalMobile', '+966501122334')
        ->set('addressAr', 'الرياض، حي العليا')
        ->set('naStreetAr', 'شارع الأمير')
        ->set('naCityAr', 'الرياض')
        ->call('saveLegal')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $legal = BusinessSetting::primary()->fresh();
    expect(data_get($legal->settings, 'business.legal.cr_number'))->toBe('1011223344')
        ->and(data_get($legal->settings, 'business.legal.name_ar'))->toBe('شركة أنابيب')
        ->and(data_get($legal->settings, 'legal_entity.vat_number'))->toBe('311122393500003')
        ->and(data_get($legal->settings, 'business.legal.mobile'))->toBe('+966501122334')
        ->and(data_get($legal->settings, 'business.legal.address_ar'))->toBe('الرياض، حي العليا');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'legal')
        ->assertSet('legalNameAr', 'شركة أنابيب')
        ->assertSet('addressAr', 'الرياض، حي العليا')
        ->assertSet('naStreetAr', 'شارع الأمير');
});

it('persists contact tab on mysql install.sql baseline', function () {
    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'contact')
        ->set('phone', '+966 11 445 8877')
        ->call('saveContact')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'business.contact.phone'))
        ->toBe('+966 11 445 8877');
});

it('persists hours invoicing and extras tabs on mysql install.sql baseline', function () {
    $baseWeekdays = BusinessSetting::primary()->settings['business']['hours']['weekdays'] ?? [];

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'hours')
        ->set('hoursNote', 'Closed on government holidays')
        ->set('weekdays', $baseWeekdays)
        ->call('saveHours')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'business.hours.note'))
        ->toBe('Closed on government holidays');

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'invoicing')
        ->set('invoicingIntegrationEnabled', false)
        ->set('paymentTermsDays', 14)
        ->call('saveInvoicing')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $fresh = BusinessSetting::primary()->fresh();
    expect(data_get($fresh->settings, 'invoicing.integration_enabled'))->toBeFalse()
        ->and(data_get($fresh->settings, 'invoicing.payment_terms_days'))->toBe(14);

    Livewire::actingAs($this->owner, 'staff')
        ->test(BusinessSettings::class)
        ->set('section', 'extras')
        ->set('plumberLicense', 'PLM-44501')
        ->call('saveExtras')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    expect(data_get(BusinessSetting::primary()->fresh()->settings, 'business.theme_extras.license_number'))
        ->toBe('PLM-44501');
});