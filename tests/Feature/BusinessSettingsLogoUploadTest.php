<?php

use App\Livewire\Admin\BusinessSettings;
use App\Models\BusinessSetting;
use App\Models\StaffUser;
use App\Support\Upload\LogoUploadConstraints;
use Database\Seeders\DemoFancyCoffeeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    (new DemoFancyCoffeeSeeder)->run();
    $this->cafe = BusinessSetting::query()->firstOrFail();
    $this->owner = StaffUser::query()
        ->where('email', 'owner@fancy-coffee.test')->firstOrFail();
    $this->actingAs($this->owner, 'staff');
});

it('maps oversize logo server rule to a friendly message', function () {
    $file = UploadedFile::fake()->create('logo.png', 2048, 'image/png');
    $validator = validator(
        ['logoUpload' => $file],
        ['logoUpload' => LogoUploadConstraints::componentRules()],
        LogoUploadConstraints::validationMessages(),
    );

    expect($validator->fails())->toBeTrue();

    $message = $validator->errors()->first('logoUpload');
    expect($message)->toBe(__('business.logo_upload.errors.too_big', ['limit' => '1 MB']))
        ->and($message)->not->toContain('logoUpload');
});

it('rejects tiny logo dimensions with friendly message', function () {
    $component = Livewire::test(BusinessSettings::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 32, 32));

    $msg = $component->errors()->get('logoUpload')[0] ?? '';

    expect($msg)->toBe(__('business.logo_upload.errors.too_small', ['min' => LogoUploadConstraints::MIN_WIDTH]));
});

it('persists logo via saveBrand independent of client resize', function () {
    $fresh = $this->cafe->fresh();

    Livewire::test(BusinessSettings::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 128, 128))
        ->set('displayNameEn', data_get($fresh->settings, 'legal_entity.name_en', 'Fancy Coffee'))
        ->set('displayNameAr', data_get($fresh->settings, 'legal_entity.name_ar', 'ف'))
        ->set('brandColorKey', 'navy')
        ->call('saveBrand')
        ->assertHasNoErrors()
        ->assertSet('logoAwaitingSave', false)
        ->assertDispatched('business-logo-saved');

    $logoUrl = data_get($fresh->fresh()->settings, 'theme.logo_url');
    expect($logoUrl)->not->toBeNull()
        ->and($logoUrl)->toStartWith('img/tenants/')
        ->and(File::exists(public_path($logoUrl)))->toBeTrue();
});

it('marks logo awaiting save after temp upload', function () {
    Livewire::test(BusinessSettings::class)
        ->set('logoUpload', UploadedFile::fake()->image('logo.png', 128, 128))
        ->assertSet('logoAwaitingSave', true);
});

it('dispatches business-logo-removed when logo is cleared', function () {
    $fresh = $this->cafe->fresh();
    $settings = $fresh->settings ?? [];
    data_set($settings, 'theme.logo_url', 'img/tenants/'.$fresh->id.'/logo-test.webp');
    $fresh->forceFill(['settings' => $settings])->save();

    Livewire::test(BusinessSettings::class)
        ->call('removeLogo')
        ->assertSet('currentLogo', null)
        ->assertDispatched('business-logo-removed');
});

it('does not reference livewire temporaryUrl for logo preview in the form', function () {
    $blade = file_get_contents(resource_path('views/components/form/logo-upload.blade.php'));

    expect($blade)->not->toContain('temporaryUrl')
        ->not->toContain('pendingTemporaryUrl');
});

it('exposes upload diagnostics via health check', function () {
    $this->get('/healthz')
        ->assertOk()
        ->assertJsonPath('checks.uploads.livewire_tmp_writable', true)
        ->assertJsonStructure(['checks' => ['uploads' => ['upload_max_filesize', 'post_max_size']]]);
});
