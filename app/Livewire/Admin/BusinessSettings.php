<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\DispatchesToasts;
use App\Models\BusinessSetting;
use App\Rules\SaudiVatNumber;
use App\Services\Business\BusinessSettingsService;
use App\Support\Geo\GeoMapsUrl;
use App\Support\BusinessHours;
use App\Support\BusinessProfile;
use App\Support\Upload\LogoUploadConstraints;
use Illuminate\Support\Facades\Log;
use App\Business\BusinessContext;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
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

    public string $addressEn = '';

    public string $addressAr = '';

    public string $naBuilding = '';

    public string $naStreet = '';

    public string $naDistrict = '';

    public string $naCity = '';

    public string $naPostal = '';

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

    /** @var list<string> */
    public array $tabsWithErrors = [];

    public function mount(BusinessSettingsService $service): void
    {
        $this->resolveBusiness();
        $this->authorize('business.manage');
        $this->businessId = $this->business->id;
        BusinessContext::set($this->business);
        $business = $service->ensureHydrated($this->business);
        $this->hydrateFromBusiness($business);
    }

    public function setSection(string $section): void
    {
        $allowed = ['brand', 'legal', 'invoicing', 'contact', 'hours', 'branches', 'extras'];
        if (in_array($section, $allowed, true)) {
            $this->section = $section;
            $this->dispatch('business-tab-shown', section: $section);
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
        $business = $this->business();

        $rules = [
            'displayNameEn' => 'required|string|max:160',
            'displayNameAr' => 'required|string|max:160',
            'displayNameBn' => 'nullable|string|max:160',
            'taglineEn' => 'nullable|string|max:240',
            'taglineAr' => 'nullable|string|max:240',
            'brandColorKey' => ['required', Rule::in(array_keys(BusinessProfile::brandColorOptions($business)))],
            'logoUpload' => LogoUploadConstraints::componentRules(),
        ];
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
    }

    public function removeLogo(): void
    {
        $this->authorize('business.manage');
        $business = $this->business();
        $settings = $business->settings ?? [];
        data_set($settings, 'theme.logo_url', null);
        data_set($settings, 'business.brand.favicon_path', null);
        $business->forceFill(['settings' => $settings])->save();
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

        $service->saveInvoicing($this->business(), [
            'integration_enabled' => $this->invoicingIntegrationEnabled,
        ], auth('staff')->user());

        $this->toastSuccess(__('business.invoicing.saved'));
    }

    public function saveLegal(BusinessSettingsService $service): void
    {
        $this->authorize('business.manage');
        $this->section = 'legal';
        $this->resetErrorBag();
        $this->tabsWithErrors = [];
        try {
            $this->runSectionValidation([
            'legalNameEn' => 'required|string|max:200',
            'legalNameAr' => 'required|string|max:200',
            'vatNumber' => ['required', 'string', new SaudiVatNumber],
            'crNumber' => 'required|string|max:32',
            'addressEn' => 'required|string|max:500',
            'addressAr' => 'required|string|max:500',
            'naBuilding' => 'required|string|max:20',
            'naStreet' => 'required|string|max:120',
            'naDistrict' => 'required|string|max:120',
            'naCity' => 'required|string|max:80',
            'naPostal' => 'required|digits:5',
            'naAdditional' => 'nullable|string|max:20',
            ], 'legal');
        } catch (ValidationException) {
            return;
        }

        $service->saveLegal($this->business(), [
            'name_en' => $this->legalNameEn,
            'name_ar' => $this->legalNameAr,
            'vat_number' => $this->vatNumber,
            'cr_number' => $this->crNumber,
            'address_en' => $this->addressEn,
            'address_ar' => $this->addressAr,
            'national_address' => [
                'building' => $this->naBuilding,
                'street' => $this->naStreet,
                'district' => $this->naDistrict,
                'city' => $this->naCity,
                'postal_code' => $this->naPostal,
                'additional' => $this->naAdditional,
            ],
        ], auth('staff')->user());

        $this->toastSuccess(__('business.messages.saved'));
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

        $service->saveContact($this->business(), [
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

        $this->lat = $lat;
        $this->lng = $lng;
        $this->mapsUrl = $mapsUrl;

        $this->toastSuccess(__('business.messages.saved'));
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

        $service->saveHours($this->business(), [
            'weekdays' => $this->weekdays,
            'note' => $this->hoursNote,
        ], auth('staff')->user());

        $this->toastSuccess(__('business.messages.saved'));
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
        $service->saveThemeExtras($business, [
            'emergency_24_7' => $this->plumberEmergency,
            'license_number' => $this->plumberLicense,
            'service_areas' => $this->plumberServiceAreas,
            'response_time_en' => $this->plumberResponseEn,
            'response_time_ar' => $this->plumberResponseAr,
        ], auth('staff')->user());

        $this->toastSuccess(__('business.messages.saved'));
    }

    public function render(): View
    {
        $business = $this->business();

        return view('livewire.admin.business-settings', [
            'business' => $business,
            'colorOptions' => BusinessProfile::brandColorOptions($business),
            'teamUrl' => route('admin.team'),
        ]);
    }

    private function business(): BusinessSetting
    {
        return BusinessSetting::query()->findOrFail($this->businessId);
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
        $this->brandColorKey = (string) ($brand['brand_color_key'] ?? BusinessProfile::primaryColorKey($business));
        $this->currentLogo = data_get($settings, 'theme.logo_url');

        $this->legalNameEn = (string) ($legal['name_en'] ?? '');
        $this->legalNameAr = (string) ($legal['name_ar'] ?? '');
        $this->vatNumber = (string) ($legal['vat_number'] ?? '');
        $this->crNumber = (string) ($legal['cr_number'] ?? '');
        $this->addressEn = (string) ($legal['address_en'] ?? $legal['address'] ?? '');
        $this->addressAr = (string) ($legal['address_ar'] ?? '');
        $na = (array) ($legal['national_address'] ?? []);
        $this->naBuilding = (string) ($na['building'] ?? '');
        $this->naStreet = (string) ($na['street'] ?? '');
        $this->naDistrict = (string) ($na['district'] ?? '');
        $this->naCity = (string) ($na['city'] ?? '');
        $this->naPostal = (string) ($na['postal_code'] ?? '');
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
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     */
    private function runSectionValidation(array $rules, string $section, array $messages = []): void
    {
        try {
            Validator::make($this->only(array_keys($rules)), $rules, $messages)->validate();
        } catch (ValidationException $e) {
            $this->section = $section;
            $this->tabsWithErrors = [$section];
            $this->setErrorBag($e->validator->errors());
            $this->toastWarning(__('business.validation.section_failed', [
                'section' => __('business.sections.'.$section),
            ]), dedupe: false);
            throw $e;
        }
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
