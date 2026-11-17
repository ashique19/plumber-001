<?php

namespace App\Livewire\Admin;

use App\Enums\PrintDocumentTemplate;
use App\Livewire\Concerns\DispatchesToasts;
use App\Models\BusinessSetting;
use App\Rules\SaudiVatNumber;
use App\Services\Business\BusinessSettingsService;
use App\Support\Geo\GeoMapsUrl;
use App\Services\Invoicing\ZHeart\ZHeartClient;
use App\Support\BusinessHours;
use App\Support\Invoicing\ZHeartCredentials;
use App\Support\BusinessProfile;
use App\Support\Upload\LogoUploadConstraints;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Business\BusinessContext;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.pos')]
class BusinessSettings extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use WithFileUploads;

    public int $businessId;

    public string $section = 'brand';

    public string $displayNameEn = '';

    public string $displayNameAr = '';

    public string $displayNameBn = '';

    public string $taglineEn = '';

    public string $taglineAr = '';

    public string $brandColorKey = '';

    public $logoUpload = null;

    public bool $logoAwaitingSave = false;

    public ?string $currentLogo = null;

    public string $legalNameEn = '';

    public string $legalNameAr = '';

    public string $vatNumber = '';

    public string $crNumber = '';

    public string $unifiedNumber = '';

    public string $legalEmail = '';

    public string $legalMobile = '';

    public string $legalMobileSecondary = '';

    public string $shortNationalAddress = '';

    public string $addressEn = '';

    public string $addressAr = '';

    public string $naBuilding = '';

    public string $naStreet = '';

    public string $naStreetAr = '';

    public string $naDistrict = '';

    public string $naDistrictAr = '';

    public string $naCity = '';

    public string $naCityAr = '';

    public string $naPostal = '';

    public string $naCountry = 'SA';

    public string $naAdditional = '';

    public string $phone = '';

    public string $whatsapp = '';

    public string $email = '';

    public string $website = '';

    public string $instagram = '';

    public string $socialX = '';

    public string $tiktok = '';

    public string $snapchat = '';

    public string $mapsUrl = '';

    public string $lat = '';

    public string $lng = '';

    /** @var list<array{key: string, closed: bool, open: string, close: string}> */
    public array $weekdays = [];

    public string $hoursNote = '';

    public bool $plumberEmergency = false;

    public string $plumberLicense = '';

    public string $plumberServiceAreas = '';

    public string $plumberResponseEn = '';

    public string $plumberResponseAr = '';

    public bool $invoicingIntegrationEnabled = true;

    public int $paymentTermsDays = 30;

    public string $invoicePrintTemplate = 'modern';

    public string $quotationPrintTemplate = 'modern';

    public string $zheartBaseUrl = '';

    public string $zheartApiKeyInput = '';

    public bool $zheartReplaceKeyOpen = false;

    public bool $zheartHasStoredKey = false;

    public ?string $zheartKeyLast4 = null;

    public bool $zheartUsingEnvFallback = false;

    public bool $zheartBaseUrlFromEnv = false;

    public ?string $zheartTestStatus = null;

    public string $zheartTestMessage = '';

    /** @var list<string> */
    public array $tabsWithErrors = [];

    public function mount(BusinessSettingsService $service): void
    {
        $this->resolveBusiness();
        $this->authorize('business.manage');
        $this->businessId = $this->business->id;
        BusinessContext::set($this->business);
        $business = $service->ensureHydrated($this->business);
        $this->syncBusinessState($business);
    }

    public function setSection(string $section): void
    {
        $allowed = ['brand', 'legal', 'invoicing', 'contact', 'hours', 'branches', 'extras'];
        if ($section === 'zheart') {
            $section = 'invoicing';
        }
        if (in_array($section, $allowed, true)) {
            if ($section !== 'brand' && ! $this->logoAwaitingSave) {
                $this->logoUpload = null;
            }
            $this->section = $section;
            $this->dispatch('business-tab-shown', section: $section);
        }
    }

    public function updatedSection(mixed $value): void
    {
        if (is_string($value)) {
            $this->setSection($value);
        }
    }

    public function updatedLat(mixed $value): void
    {
        $this->lat = $this->normalizeCoordinate($value);
    }

    public function updatedLng(mixed $value): void
    {
        $this->lng = $this->normalizeCoordinate($value);
    }

    public function updatedLogoUpload(): void
    {
        $this->resetValidation('logoUpload');

        if ($this->logoUpload === null) {
            $this->logoAwaitingSave = false;

            return;
        }

        $ext = strtolower((string) $this->logoUpload->getClientOriginalExtension());
        if ($ext === 'svg') {
            $this->logoAwaitingSave = ! $this->getErrorBag()->has('logoUpload');

            return;
        }

        $path = $this->logoUpload->getRealPath();
        if (! is_string($path) || ! is_readable($path)) {
            $this->rejectLogoUpload(__('business.logo_upload.errors.processing_failed'));

            return;
        }

        $size = @getimagesize($path);
        if ($size === false) {
            $this->rejectLogoUpload(__('business.logo_upload.errors.invalid_type'));

            return;
        }

        [$width, $height] = $size;
        if ($width < LogoUploadConstraints::MIN_WIDTH || $height < LogoUploadConstraints::MIN_HEIGHT) {
            $this->rejectLogoUpload(__('business.logo_upload.errors.too_small', [
                'min' => LogoUploadConstraints::MIN_WIDTH,
            ]));
        }

        $this->logoAwaitingSave = $this->logoUpload !== null && ! $this->getErrorBag()->has('logoUpload');
    }

    public function validateLiveField(string $property): void
    {
        $rules = $this->liveRulesForProperty($property);
        if ($rules === null) {
            return;
        }

        $this->validateOnly($property, [$property => $rules]);
    }

    public function saveBrand(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'brand';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];

        Log::info('business.settings.save_invoked', [
            'section' => 'brand',
            'business_id' => $this->businessId,
        ]);

        $business = $this->business();
        $this->brandColorKey = BusinessProfile::normalizeBrandColorKey($business, $this->brandColorKey);

        $rules = [
            'displayNameEn' => 'nullable|string|max:160|required_without:displayNameBn',
            'displayNameAr' => 'nullable|string|max:160',
            'displayNameBn' => 'nullable|string|max:160|required_without:displayNameEn',
            'taglineEn' => 'nullable|string|max:240',
            'taglineAr' => 'nullable|string|max:240',
            'brandColorKey' => ['required', Rule::in(array_keys(BusinessProfile::brandColorOptions($business)))],
        ];
        if ($this->section === 'brand' && $this->logoAwaitingSave && $this->logoUpload !== null) {
            $rules['logoUpload'] = LogoUploadConstraints::componentRules();
        }
        try {
            $this->runSectionValidation($rules, 'brand', LogoUploadConstraints::validationMessages());
        } catch (ValidationException) {
            return;
        }

        try {
            $business = $service->saveBrand($business, [
                'display_name_en' => $this->displayNameEn,
                'display_name_ar' => $this->displayNameAr,
                'display_name_bn' => $this->displayNameBn,
                'tagline_en' => $this->taglineEn,
                'tagline_ar' => $this->taglineAr,
                'brand_color_key' => $this->brandColorKey,
            ], auth('staff')->user(), $this->logoUpload);
        } catch (\InvalidArgumentException $e) {
            $this->addError('logoUpload', $e->getMessage());
            $this->toastError($e->getMessage(), persistent: true);

            return;
        } catch (Throwable $e) {
            $this->failSave('brand', $e);

            return;
        }

        $hadNewLogo = $this->logoUpload !== null;
        $this->logoUpload = null;
        $this->logoAwaitingSave = false;
        $this->currentLogo = data_get($business->settings, 'theme.logo_url');

        if ($hadNewLogo && filled($this->currentLogo)) {
            $this->dispatch('business-logo-saved', publicUrl: asset($this->currentLogo));
            $this->toastSuccess(__('business.messages.saved_logo'));
        } else {
            $this->toastSuccess(__('business.messages.saved'));
        }

        Log::info('business.settings.saved', [
            'section' => 'brand',
            'business_id' => $this->businessId,
        ]);

        $this->syncBusinessState($business);
    }

    public function removeLogo(): void
    {
        $this->authorize('business.manage');
        $business = $this->business();
        $settings = $business->settings ?? [];
        data_set($settings, 'theme.logo_url', null);
        data_set($settings, 'business.brand.favicon_path', null);
        $business->writeSettings($settings);
        $this->currentLogo = null;
        $this->logoUpload = null;
        $this->logoAwaitingSave = false;
        $this->dispatch('business-logo-removed');
        $this->toastSuccess(__('business.messages.logo_removed'));
    }

    public function saveInvoicing(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'invoicing';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];

        try {
            $business = $service->saveInvoicing($this->business(), [
                'integration_enabled' => $this->invoicingIntegrationEnabled,
                'payment_terms_days' => $this->paymentTermsDays,
                'invoice_print_template' => $this->invoicePrintTemplate,
                'quotation_print_template' => $this->quotationPrintTemplate,
            ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('invoicing', $e);

            return;
        }

        $this->toastSuccess(__('business.invoicing.saved'));
        $this->syncBusinessState($business);
    }

    public function saveLegal(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'legal';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];

        Log::info('business.settings.save_invoked', [
            'section' => 'legal',
            'business_id' => $this->businessId,
        ]);

        try {
            $this->runSectionValidation([
            'legalNameEn' => 'nullable|string|max:200',
            'legalNameAr' => 'nullable|string|max:200',
            'vatNumber' => ['nullable', 'string', new SaudiVatNumber],
            'crNumber' => 'nullable|string|max:32',
            'unifiedNumber' => 'nullable|string|max:32',
            'legalEmail' => 'nullable|email|max:120',
            'legalMobile' => 'nullable|string|max:40',
            'legalMobileSecondary' => 'nullable|string|max:40',
            'shortNationalAddress' => 'nullable|string|max:500',
            'addressEn' => 'nullable|string|max:500',
            'addressAr' => 'nullable|string|max:500',
            'naBuilding' => 'nullable|string|max:20',
            'naStreet' => 'nullable|string|max:120',
            'naStreetAr' => 'nullable|string|max:120',
            'naDistrict' => 'nullable|string|max:120',
            'naDistrictAr' => 'nullable|string|max:120',
            'naCity' => 'nullable|string|max:80',
            'naCityAr' => 'nullable|string|max:80',
            'naPostal' => 'nullable|digits:5',
            'naCountry' => 'nullable|string|size:2',
            'naAdditional' => 'nullable|string|max:20',
            ], 'legal');
        } catch (ValidationException) {
            return;
        }

        try {
            $business = $service->saveLegal($this->business(), [
            'name_en' => $this->legalNameEn,
            'name_ar' => $this->legalNameAr,
            'vat_number' => $this->vatNumber,
            'cr_number' => $this->crNumber,
            'unified_number' => $this->unifiedNumber,
            'email' => $this->legalEmail,
            'mobile' => $this->legalMobile,
            'mobile_secondary' => $this->legalMobileSecondary,
            'short_national_address' => $this->shortNationalAddress,
            'address_en' => $this->addressEn,
            'address_ar' => $this->addressAr,
            'national_address' => array_filter([
                'building' => $this->naBuilding,
                'street' => $this->naStreet,
                'street_ar' => $this->naStreetAr,
                'district' => $this->naDistrict,
                'district_ar' => $this->naDistrictAr,
                'city' => $this->naCity,
                'city_ar' => $this->naCityAr,
                'postal_code' => $this->naPostal,
                'country' => $this->naCountry !== '' ? strtoupper($this->naCountry) : 'SA',
                'additional' => $this->naAdditional,
            ], static fn (string $v): bool => $v !== ''),
        ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('legal', $e);

            return;
        }

        $this->toastSuccess(__('business.messages.saved'));
        Log::info('business.settings.saved', [
            'section' => 'legal',
            'business_id' => $this->businessId,
        ]);
        $this->syncBusinessState($business);
    }

    public function saveContact(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'contact';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];
        $this->lat = $this->normalizeCoordinate($this->lat);
        $this->lng = $this->normalizeCoordinate($this->lng);
        try {
            $this->runSectionValidation([
            'phone' => 'nullable|string|max:40',
            'whatsapp' => 'nullable|string|max:40',
            'email' => ['nullable', 'email:rfc,filter', 'max:120'],
            'website' => 'nullable|url|max:200',
            'instagram' => 'nullable|string|max:80',
            'socialX' => 'nullable|string|max:80',
            'tiktok' => 'nullable|string|max:80',
            'snapchat' => 'nullable|string|max:80',
            'mapsUrl' => 'nullable|url|max:500',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            ], 'contact');
        } catch (ValidationException) {
            return;
        }

        $lat = $this->lat !== '' && is_numeric($this->lat) ? (string) round((float) $this->lat, 6) : '';
        $lng = $this->lng !== '' && is_numeric($this->lng) ? (string) round((float) $this->lng, 6) : '';
        $mapsUrl = trim($this->mapsUrl);
        if ($mapsUrl === '' && $lat !== '' && $lng !== '') {
            $mapsUrl = GeoMapsUrl::googleQuery($lat, $lng);
        }

        try {
            $business = $service->saveContact($this->business(), [
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'email' => $this->email,
            'website' => $this->website,
            'instagram' => $this->instagram,
            'x' => $this->socialX,
            'tiktok' => $this->tiktok,
            'snapchat' => $this->snapchat,
            'maps_url' => $mapsUrl,
            'lat' => $lat,
            'lng' => $lng,
        ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('contact', $e);

            return;
        }

        $this->lat = $lat;
        $this->lng = $lng;
        $this->mapsUrl = $mapsUrl;

        $this->toastSuccess(__('business.messages.saved'));
        $this->syncBusinessState($business);
    }

    public function saveHours(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'hours';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];
        $this->weekdays = BusinessProfile::normalizeWeekdays($this->weekdays);
        try {
            $this->runSectionValidation([
            'hoursNote' => 'nullable|string|max:500',
            'weekdays' => 'required|array',
            'weekdays.*.key' => 'required|string|in:saturday,sunday,monday,tuesday,wednesday,thursday,friday',
            'weekdays.*.closed' => 'boolean',
            'weekdays.*.open' => 'nullable|date_format:H:i',
            'weekdays.*.close' => 'nullable|date_format:H:i',
            ], 'hours');
        } catch (ValidationException) {
            return;
        }

        try {
            $business = $service->saveHours($this->business(), [
                'weekdays' => $this->weekdays,
                'note' => $this->hoursNote,
            ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('hours', $e);

            return;
        }

        $this->toastSuccess(__('business.messages.saved'));
        $this->syncBusinessState($business);
    }

    public function saveExtras(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'extras';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];
        $business = $this->business();
        try {
            $this->runSectionValidation([
                'plumberLicense' => 'nullable|string|max:80',
                'plumberServiceAreas' => 'nullable|string|max:1000',
                'plumberResponseEn' => 'nullable|string|max:240',
                'plumberResponseAr' => 'nullable|string|max:240',
            ], 'extras');
        } catch (ValidationException) {
            return;
        }
        try {
            $business = $service->saveThemeExtras($business, [
                'emergency_24_7' => $this->plumberEmergency,
                'license_number' => $this->plumberLicense,
                'service_areas' => $this->plumberServiceAreas,
                'response_time_en' => $this->plumberResponseEn,
                'response_time_ar' => $this->plumberResponseAr,
            ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('extras', $e);

            return;
        }

        $this->toastSuccess(__('business.messages.saved'));
        $this->syncBusinessState($business);
    }

    public function beginZheartReplaceKey(): void
    {
        $this->assertOwner();
        $this->zheartReplaceKeyOpen = true;
        $this->zheartApiKeyInput = '';
    }

    public function updatedZheartBaseUrl(): void
    {
        $stored = trim((string) data_get($this->business->settings, 'zheart.base_url', ''));
        if ($stored !== '') {
            $this->zheartBaseUrlFromEnv = false;

            return;
        }

        $env = rtrim((string) config('zheart.base_url', ''), '/');
        $current = rtrim(trim($this->zheartBaseUrl), '/');
        $this->zheartBaseUrlFromEnv = $env !== '' && $current === $env;
    }

    public function saveZheart(BusinessSettingsService $service): void
    {
        $this->assertOwner();
        $this->section = 'invoicing';
        $this->tabsWithErrors = [];

        $rules = [
            'zheartBaseUrl' => ['nullable', 'string', 'max:255', 'regex:/^https?:\/\/.+/i'],
        ];
        if (trim($this->zheartApiKeyInput) !== '') {
            $rules['zheartApiKeyInput'] = ['required', 'string', 'min:16', 'max:512'];
        }

        try {
            $this->runSectionValidation($rules, 'invoicing');
        } catch (ValidationException) {
            return;
        }

        try {
            $business = $service->saveZheart($this->business(), [
                'base_url' => $this->zheartBaseUrl,
                'api_key' => trim($this->zheartApiKeyInput) !== '' ? trim($this->zheartApiKeyInput) : null,
            ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('invoicing', $e);

            return;
        }

        $this->zheartApiKeyInput = '';
        $this->zheartReplaceKeyOpen = false;
        $this->toastSuccess(__('business.messages.saved'));
        $this->syncBusinessState($business);
    }

    public function removeZheartKey(BusinessSettingsService $service): void
    {
        $this->assertOwner();
        $this->section = 'invoicing';

        try {
            $business = $service->saveZheart($this->business(), [
                'remove_api_key' => true,
            ], auth('staff')->user());
        } catch (Throwable $e) {
            $this->failSave('invoicing', $e);

            return;
        }

        $this->zheartApiKeyInput = '';
        $this->zheartReplaceKeyOpen = false;
        $this->toastSuccess(__('business.zheart.key_removed'));
        $this->syncBusinessState($business);
    }

    public function testZheartConnection(ZHeartClient $client): void
    {
        $this->assertOwner();
        $this->section = 'invoicing';

        $business = $this->business();
        $base = trim($this->zheartBaseUrl);
        if ($base === '') {
            $base = ZHeartCredentials::baseUrl($business);
        } else {
            $base = rtrim($base, '/');
        }

        $key = trim($this->zheartApiKeyInput);
        if ($key === '') {
            $key = ZHeartCredentials::apiKey($business);
        }

        $result = $client->testConnection($base, $key);
        $this->zheartTestStatus = $result['ok'] ? 'ok' : 'error';
        $this->zheartTestMessage = $result['message'];

        if ($result['ok']) {
            $this->toastSuccess($result['message']);
        } else {
            $this->toastError($result['message']);
        }
    }

    public function render(): View
    {
        $business = $this->business();

        $staffUser = auth('staff')->user();

        return view('livewire.admin.business-settings', [
            'business' => $business,
            'colorOptions' => BusinessProfile::brandColorOptions($business),
            'teamUrl' => route('admin.team'),
            'usersUrl' => ($staffUser && $staffUser->hasRole('owner')) ? route('admin.users') : null,
            'isOwner' => $this->isOwner(),
        ]);
    }

    private function isOwner(): bool
    {
        return auth('staff')->user()?->hasRole('owner') ?? false;
    }

    private function assertOwner(): void
    {
        abort_unless($this->isOwner(), 403);
    }

    private function business(): BusinessSetting
    {
        $primary = BusinessSetting::primary();

        if ($this->businessId !== $primary->id) {
            Log::warning('business.settings.business_id_drift', [
                'component_business_id' => $this->businessId,
                'primary_id' => $primary->id,
            ]);
            $this->businessId = $primary->id;
        }

        $row = BusinessSetting::query()->find($this->businessId);

        if ($row === null) {
            $row = $primary;
            $this->businessId = $row->id;
        }

        return $row;
    }

    protected function validationAttributes(): array
    {
        return [
            'logoUpload' => __('business.brand.logo'),
        ];
    }

    private function rejectLogoUpload(string $message): void
    {
        Log::info('business.logo_upload.rejected', ['message' => $message]);
        $this->addError('logoUpload', $message);
        $this->logoUpload = null;
        $this->logoAwaitingSave = false;
    }

    private function normalizeCoordinate(mixed $value): string
    {
        if ($value === null || $value === '' || $value === false) {
            return '';
        }
        if (is_array($value)) {
            return '';
        }
        if (is_numeric($value)) {
            return (string) round((float) $value, 6);
        }

        return BusinessProfile::scalarString($value);
    }

    private function hydrateFromBusiness(BusinessSetting $business): void
    {
        $settings = $business->settings ?? [];
        $brand = (array) data_get($settings, 'business.brand', []);
        $legal = (array) data_get($settings, 'business.legal', data_get($settings, 'legal_entity', []));
        $contact = (array) data_get($settings, 'business.contact', []);
        $hours = (array) data_get($settings, 'business.hours', []);
        $extras = (array) data_get($settings, 'business.theme_extras', []);

        $this->displayNameEn = (string) ($brand['display_name_en'] ?? $legal['name_en'] ?? $business->name);
        $this->displayNameAr = (string) ($brand['display_name_ar'] ?? $legal['name_ar'] ?? '');
        $this->displayNameBn = (string) ($brand['display_name_bn'] ?? '');
        $this->taglineEn = (string) ($brand['tagline_en'] ?? '');
        $this->taglineAr = (string) ($brand['tagline_ar'] ?? '');
        $this->brandColorKey = BusinessProfile::normalizeBrandColorKey(
            $business,
            $brand['brand_color_key'] ?? BusinessProfile::primaryColorKey($business),
        );
        $this->currentLogo = data_get($settings, 'theme.logo_url');

        $this->legalNameEn = (string) ($legal['name_en'] ?? '');
        $this->legalNameAr = (string) ($legal['name_ar'] ?? '');
        $this->vatNumber = (string) ($legal['vat_number'] ?? '');
        $this->crNumber = (string) ($legal['cr_number'] ?? '');
        $this->unifiedNumber = (string) ($legal['unified_number'] ?? '');
        $this->legalEmail = (string) ($legal['email'] ?? '');
        $this->legalMobile = (string) ($legal['mobile'] ?? '');
        $this->legalMobileSecondary = (string) ($legal['mobile_secondary'] ?? '');
        $this->shortNationalAddress = (string) ($legal['short_national_address'] ?? '');
        $this->addressEn = (string) ($legal['address_en'] ?? $legal['address'] ?? '');
        $this->addressAr = (string) ($legal['address_ar'] ?? '');
        $na = (array) ($legal['national_address'] ?? []);
        $this->naBuilding = (string) ($na['building'] ?? '');
        $this->naStreet = (string) ($na['street'] ?? '');
        $this->naStreetAr = (string) ($na['street_ar'] ?? '');
        $this->naDistrict = (string) ($na['district'] ?? '');
        $this->naDistrictAr = (string) ($na['district_ar'] ?? '');
        $this->naCity = (string) ($na['city'] ?? '');
        $this->naCityAr = (string) ($na['city_ar'] ?? '');
        $this->naPostal = (string) ($na['postal_code'] ?? '');
        $this->naCountry = strtoupper((string) ($na['country'] ?? 'SA')) ?: 'SA';
        $this->naAdditional = (string) ($na['additional'] ?? '');

        $this->phone = (string) ($contact['phone'] ?? '');
        $this->whatsapp = (string) ($contact['whatsapp'] ?? '');
        $this->email = (string) ($contact['email'] ?? '');
        $this->website = (string) ($contact['website'] ?? '');
        $this->instagram = (string) ($contact['instagram'] ?? '');
        $this->socialX = (string) ($contact['x'] ?? '');
        $this->tiktok = (string) ($contact['tiktok'] ?? '');
        $this->snapchat = (string) ($contact['snapchat'] ?? '');
        $this->mapsUrl = (string) ($contact['maps_url'] ?? '');
        $this->lat = (string) ($contact['lat'] ?? '');
        $this->lng = (string) ($contact['lng'] ?? '');

        $storedWeekdays = $hours['weekdays'] ?? null;
        $weekdays = BusinessProfile::normalizeWeekdays($storedWeekdays);
        if ($storedWeekdays === null || $storedWeekdays === []) {
            $inferred = BusinessHours::weekdaysFromLegacyRows(
                (array) (BusinessProfile::contentSlice($business)['hours'] ?? []),
            );
            if ($inferred !== null) {
                $weekdays = BusinessProfile::normalizeWeekdays($inferred);
            }
        }
        $this->weekdays = $weekdays;
        $this->hoursNote = BusinessProfile::scalarString($hours['note'] ?? '');

        $this->plumberEmergency = (bool) ($extras['emergency_24_7'] ?? false);
        $this->plumberLicense = (string) ($extras['license_number'] ?? '');
        $this->plumberServiceAreas = (string) ($extras['service_areas'] ?? '');
        $this->plumberResponseEn = (string) ($extras['response_time_en'] ?? '');
        $this->plumberResponseAr = (string) ($extras['response_time_ar'] ?? '');

        $this->invoicingIntegrationEnabled = $business->invoicingIntegrationEnabled();
        $this->paymentTermsDays = max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30));
        $this->invoicePrintTemplate = in_array(
            (string) data_get($business->settings, 'invoicing.invoice_print_template', 'modern'),
            PrintDocumentTemplate::values(),
            true,
        ) ? (string) data_get($business->settings, 'invoicing.invoice_print_template', 'modern') : 'modern';
        $this->quotationPrintTemplate = in_array(
            (string) data_get($business->settings, 'invoicing.quotation_print_template', 'modern'),
            PrintDocumentTemplate::values(),
            true,
        ) ? (string) data_get($business->settings, 'invoicing.quotation_print_template', 'modern') : 'modern';

        $storedBaseUrl = trim((string) data_get($business->settings, 'zheart.base_url', ''));
        $envBaseUrl = rtrim((string) config('zheart.base_url', ''), '/');
        if ($storedBaseUrl !== '') {
            $this->zheartBaseUrl = $storedBaseUrl;
            $this->zheartBaseUrlFromEnv = false;
        } elseif ($envBaseUrl !== '') {
            $this->zheartBaseUrl = $envBaseUrl;
            $this->zheartBaseUrlFromEnv = true;
        } else {
            $this->zheartBaseUrl = '';
            $this->zheartBaseUrlFromEnv = false;
        }
        $this->zheartHasStoredKey = ZHeartCredentials::hasStoredApiKey($business);
        $this->zheartKeyLast4 = ZHeartCredentials::last4ForDisplay($business);
        $this->zheartUsingEnvFallback = ! $this->zheartHasStoredKey
            && is_string(config('zheart.api_key'))
            && config('zheart.api_key') !== '';
        $this->zheartApiKeyInput = '';
        $this->zheartReplaceKeyOpen = false;
        $this->zheartTestStatus = null;
        $this->zheartTestMessage = '';

        $this->sanitizeLegacyFieldFormats();
    }

    private function sanitizeLegacyFieldFormats(): void
    {
        $postalDigits = preg_replace('/\D/', '', $this->naPostal) ?? '';
        if ($this->naPostal !== '' && strlen($postalDigits) !== 5) {
            Log::info('business.settings.legacy_field_cleared', [
                'field' => 'naPostal',
                'business_id' => $this->businessId,
            ]);
            $this->naPostal = '';
        } elseif ($postalDigits !== $this->naPostal && strlen($postalDigits) === 5) {
            $this->naPostal = $postalDigits;
        }

        if ($this->naCountry !== '' && strlen($this->naCountry) !== 2) {
            Log::info('business.settings.legacy_field_cleared', [
                'field' => 'naCountry',
                'business_id' => $this->businessId,
            ]);
            $this->naCountry = 'SA';
        }

        foreach (['legalEmail' => 'legalEmail', 'email' => 'email'] as $field => $property) {
            $value = $this->{$property};
            if ($value === '' || ! is_string($value)) {
                continue;
            }
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                Log::info('business.settings.legacy_field_cleared', [
                    'field' => $field,
                    'business_id' => $this->businessId,
                ]);
                $this->{$property} = '';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    private function runSectionValidation(array $rules, string $section, array $messages = []): void
    {
        try {
            $this->validate($rules, $messages);
        } catch (ValidationException $e) {
            $this->section = $section;
            $this->tabsWithErrors = [$section];
            $this->setErrorBag($e->validator->errors());

            Log::warning('business.settings.validation_failed', [
                'section' => $section,
                'business_id' => $this->businessId,
                'fields' => $e->validator->errors()->keys(),
            ]);

            $this->toastWarning(__('business.validation.section_failed', [
                'section' => __('business.sections.'.$section),
            ]), dedupe: false);

            $this->js(<<<'JS'
                queueMicrotask(() => {
                    const err = document.querySelector('.business-settings-page .form-field__error, .business-settings-page .form-field--invalid');
                    const field = err?.closest('.form-field') ?? err;
                    field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            JS);

            throw $e;
        }
    }

    private function syncBusinessState(BusinessSetting $business): void
    {
        $fresh = $business->fresh() ?? $business;
        $this->business = $fresh;
        $this->businessId = $fresh->id;
        BusinessContext::set($fresh);
        $this->hydrateFromBusiness($fresh);
    }

    private function failSave(string $section, Throwable $e): void
    {
        Log::error('business.settings.livewire_save_failed', [
            'section' => $section,
            'business_id' => $this->businessId,
            'message' => $e->getMessage(),
            'exception' => $e::class,
        ]);
        $this->section = $section;
        $this->tabsWithErrors = [$section];

        $message = match (true) {
            $e instanceof ValidationException => __('business.validation.section_failed', [
                'section' => __('business.sections.'.$section),
            ]),
            $e instanceof QueryException && str_contains($e->getMessage(), 'Incorrect string value') => __('business.errors.charset_save_failed'),
            default => __('business.errors.save_failed'),
        };

        $this->toastError($message, persistent: true);
    }

    private function liveRulesForProperty(string $property): ?string
    {
        if ($this->section !== 'contact') {
            return null;
        }

        return match ($property) {
            'email' => 'nullable|email:rfc,filter|max:120',
            'website' => 'nullable|url|max:200',
            'mapsUrl' => 'nullable|url|max:500',
            default => null,
        };
    }
}
