<div class="business-settings-page pb-24 lg:pb-8 min-w-0 max-w-full overflow-x-hidden" dir="{{ in_array(app()->getLocale(), ['ar', 'ur'], true) ? 'rtl' : 'ltr' }}" x-data="{ sectionTab: @entangle('section').live }">
    <header class="mb-4 min-w-0">
        <h1 class="app-page-title break-words">{{ __('business.title') }}</h1>
        <p class="app-page-lead break-words">{{ __('business.subtitle') }}</p>
    </header>

    <label class="mb-4 block min-w-0 sm:hidden">
        <span class="sr-only">{{ __('business.title') }}</span>
        <select class="app-toolbar-select w-full max-w-full" x-model="sectionTab" aria-label="{{ __('business.title') }}">
            @foreach (['brand', 'legal', 'contact', 'hours', 'branches', 'extras'] as $key)
                <option value="{{ $key }}">{{ __('business.sections.'.$key) }}</option>
            @endforeach
        </select>
    </label>

    <nav class="app-section-tabs app-section-tabs--scroll mb-4 hidden min-w-0 sm:flex" aria-label="{{ __('business.title') }}">
        @foreach (['brand', 'legal', 'contact', 'hours', 'branches', 'extras'] as $key)
            <button
                type="button"
                wire:click="setSection('{{ $key }}')"
                @click="sectionTab = '{{ $key }}'"
                @class([
                    'app-segment-tab',
                    'app-segment-tab--active' => $section === $key,
                ])
                @if ($section === $key) data-active-section @endif
            >
                <span class="inline-flex items-center gap-1">
                    {{ __('business.sections.'.$key) }}
                    @if (in_array($key, $tabsWithErrors, true))
                        <span class="inline-block h-2 w-2 rounded-full bg-amber-600" aria-hidden="true" title="{{ __('business.validation.tab_has_errors') }}"></span>
                    @endif
                </span>
            </button>
        @endforeach
    </nav>

    @if ($section === 'brand')
        <form wire:submit="saveBrand" class="app-card flex flex-col gap-4 p-4">
            <x-form.input :label="__('business.brand.display_name_en')" name="displayNameEn" wire:model.blur="displayNameEn" :liveValidate="false" required />
            <x-form.input :label="__('business.brand.display_name_ar')" name="displayNameAr" wire:model.blur="displayNameAr" :liveValidate="false" required />
            <x-form.input :label="__('business.brand.display_name_bn')" name="displayNameBn" wire:model.blur="displayNameBn" :liveValidate="false" />
            <x-form.input :label="__('business.brand.tagline_en')" name="taglineEn" wire:model.blur="taglineEn" :liveValidate="false" />
            <x-form.input :label="__('business.brand.tagline_ar')" name="taglineAr" wire:model.blur="taglineAr" :liveValidate="false" />
            <x-form.select :label="__('business.brand.brand_color')" name="brandColorKey" wire:model="brandColorKey" :liveValidate="false" required>
                @foreach ($colorOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-form.select>
            <x-form.logo-upload
                :label="__('business.brand.logo')"
                wire-model="logoUpload"
                :preview-url="$currentLogo"
                :awaiting-save="$logoAwaitingSave"
                remove-action="removeLogo"
            />
            <div class="flex justify-end">
                <button type="submit" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:loading.attr="disabled">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'legal')
        <form wire:submit="saveLegal" class="app-card flex flex-col gap-4 p-4">
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">{{ __('business.legal.zheart_note') }}</p>
            <x-form.input :label="__('business.legal.name_en')" name="legalNameEn" wire:model.blur="legalNameEn" :liveValidate="false" required />
            <x-form.input :label="__('business.legal.name_ar')" name="legalNameAr" wire:model.blur="legalNameAr" :liveValidate="false" required />
            <x-form.input :label="__('business.legal.vat')" name="vatNumber" wire:model.blur="vatNumber" :liveValidate="false" required />
            <x-form.input :label="__('business.legal.cr')" name="crNumber" wire:model.blur="crNumber" :liveValidate="false" required />
            <x-form.input :label="__('business.legal.address_en')" name="addressEn" wire:model.blur="addressEn" :liveValidate="false" required />
            <x-form.input :label="__('business.legal.address_ar')" name="addressAr" wire:model.blur="addressAr" :liveValidate="false" required />
            <h3 class="form-section__title">{{ __('business.legal.national_address') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-form.input :label="__('business.legal.na_building')" name="naBuilding" wire:model.blur="naBuilding" :liveValidate="false" required />
                <x-form.input :label="__('business.legal.na_street')" name="naStreet" wire:model.blur="naStreet" :liveValidate="false" required />
                <x-form.input :label="__('business.legal.na_district')" name="naDistrict" wire:model.blur="naDistrict" :liveValidate="false" required />
                <x-form.input :label="__('business.legal.na_city')" name="naCity" wire:model.blur="naCity" :liveValidate="false" required />
                <x-form.input :label="__('business.legal.na_postal')" name="naPostal" wire:model.blur="naPostal" :liveValidate="false" required />
                <x-form.input :label="__('business.legal.na_additional')" name="naAdditional" wire:model.blur="naAdditional" :liveValidate="false" />
            </div>
            <div class="flex justify-end">
                <button type="submit" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:loading.attr="disabled">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'contact')
        <form wire:submit="saveContact" class="app-card flex flex-col gap-6 p-4" novalidate>
            <fieldset class="flex flex-col gap-4 border-0 p-0">
                <legend class="form-section__title mb-1 w-full">{{ __('business.contact.section_phone') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input :label="__('business.contact.phone')" name="phone" wire:model.blur="phone" :liveValidate="false" />
                    <x-form.input :label="__('business.contact.whatsapp')" name="whatsapp" wire:model.blur="whatsapp" :liveValidate="false" />
                    <x-form.input :label="__('business.contact.email')" name="email" type="email" wire:model.blur="email" :liveValidate="true" wrapperClass="sm:col-span-2" />
                    <x-form.input :label="__('business.contact.website')" name="website" wire:model.blur="website" :liveValidate="false" wrapperClass="sm:col-span-2" />
                </div>
            </fieldset>
            <fieldset class="flex flex-col gap-4 border-0 p-0">
                <legend class="form-section__title mb-1 w-full">{{ __('business.contact.section_social') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input :label="__('business.contact.instagram')" name="instagram" wire:model.blur="instagram" :liveValidate="false" />
                    <x-form.input :label="__('business.contact.x')" name="socialX" wire:model.blur="socialX" :liveValidate="false" />
                    <x-form.input :label="__('business.contact.tiktok')" name="tiktok" wire:model.blur="tiktok" :liveValidate="false" />
                    <x-form.input :label="__('business.contact.snapchat')" name="snapchat" wire:model.blur="snapchat" :liveValidate="false" />
                </div>
            </fieldset>
            <fieldset class="flex flex-col gap-4 border-0 p-0">
                <legend class="form-section__title mb-1 w-full">{{ __('business.contact.section_location') }}</legend>
                <x-form.location-picker
                    lat-wire="lat"
                    lng-wire="lng"
                    maps-url-wire="mapsUrl"
                    :default-lat="(float) (is_numeric($lat) ? $lat : 24.7136)"
                    :default-lng="(float) (is_numeric($lng) ? $lng : 46.6753)"
                >
                    <x-slot name="advanced">
                        <div class="mt-2" x-data="{ contactAdvancedOpen: false }">
                            <button type="button" class="text-xs font-semibold text-[var(--plumber-safety)] underline" @click="contactAdvancedOpen = !contactAdvancedOpen" :aria-expanded="contactAdvancedOpen">
                                {{ __('business.map.advanced') }}
                            </button>
                            <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="contactAdvancedOpen" x-cloak>
                                <x-form.input :label="__('business.contact.lat')" name="lat" wire:model.blur="lat" :liveValidate="false" />
                                <x-form.input :label="__('business.contact.lng')" name="lng" wire:model.blur="lng" :liveValidate="false" />
                                <x-form.input :label="__('business.contact.maps_url')" name="mapsUrl" wire:model.blur="mapsUrl" :liveValidate="false" wrapperClass="sm:col-span-2" />
                            </div>
                        </div>
                    </x-slot>
                </x-form.location-picker>
            </fieldset>
            <div class="flex justify-end">
                <button type="submit" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:loading.attr="disabled">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'hours')
        <form wire:submit="saveHours" class="app-card flex flex-col gap-4 p-4 min-w-0 max-w-full">
            <div class="business-hours-cards flex flex-col gap-3 md:hidden">
                @foreach ($weekdays as $i => $day)
                    <fieldset class="rounded-xl border border-[var(--app-border)] p-3">
                        <legend class="mb-2 text-sm font-semibold">{{ __('business.weekdays.'.$day['key']) }}</legend>
                        <label class="mb-3 flex min-h-11 items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="weekdays.{{ $i }}.closed" class="form-toggle__input">
                            <span>{{ __('business.hours.closed_toggle') }}</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex flex-col gap-1 text-xs font-medium text-[var(--app-muted)]">
                                {{ __('business.hours.open') }}
                                <input type="time" wire:model="weekdays.{{ $i }}.open" class="form-field__control w-full min-w-0" @disabled($day['closed'] ?? false)>
                            </label>
                            <label class="flex flex-col gap-1 text-xs font-medium text-[var(--app-muted)]">
                                {{ __('business.hours.close') }}
                                <input type="time" wire:model="weekdays.{{ $i }}.close" class="form-field__control w-full min-w-0" @disabled($day['closed'] ?? false)>
                            </label>
                        </div>
                    </fieldset>
                @endforeach
            </div>
            <div class="business-hours-table hidden overflow-x-auto md:block">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-start">
                            <th class="form-field__label pb-2 pe-2 text-start">{{ __('business.hours.weekday') }}</th>
                            <th class="form-field__label pb-2 px-2 text-center">{{ __('business.hours.closed_toggle') }}</th>
                            <th class="form-field__label pb-2 px-2">{{ __('business.hours.open') }}</th>
                            <th class="form-field__label pb-2 ps-2">{{ __('business.hours.close') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($weekdays as $i => $day)
                            <tr class="border-t border-[var(--app-border)]">
                                <td class="py-2 pe-2 font-medium">{{ __('business.weekdays.'.$day['key']) }}</td>
                                <td class="px-2 py-2 text-center">
                                    <input type="checkbox" wire:model.live="weekdays.{{ $i }}.closed" class="form-toggle__input">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="time" wire:model="weekdays.{{ $i }}.open" class="form-field__control" @disabled($day['closed'] ?? false)>
                                </td>
                                <td class="ps-2 py-2">
                                    <input type="time" wire:model="weekdays.{{ $i }}.close" class="form-field__control" @disabled($day['closed'] ?? false)>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-form.input :label="__('business.hours.note_field')" name="hoursNote" wire:model.blur="hoursNote" :liveValidate="false" />
            <div class="flex justify-end">
                <button type="submit" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:loading.attr="disabled">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'branches')
        <section class="app-card flex flex-col gap-4 p-4">
            <p class="text-sm text-[var(--app-muted)]">{{ __('business.branches.intro') }}</p>
            <a href="{{ $teamUrl }}" class="tap-action-primary min-h-11 w-full text-center sm:w-auto">{{ __('business.branches.open_team') }}</a>
        </section>
    @else
        <form wire:submit="saveExtras" class="app-card flex flex-col gap-4 p-4">
            <x-form.toggle :label="__('business.extras.storefront_emergency')" name="plumberEmergency" wire:model="plumberEmergency" />
            <x-form.input :label="__('business.extras.storefront_license')" name="plumberLicense" wire:model.blur="plumberLicense" :liveValidate="false" />
            <x-form.textarea :label="__('business.extras.storefront_areas')" name="plumberServiceAreas" wire:model.blur="plumberServiceAreas" rows="3" :liveValidate="false" />
            <x-form.input :label="__('business.extras.storefront_response_en')" name="plumberResponseEn" wire:model.blur="plumberResponseEn" :liveValidate="false" />
            <x-form.input :label="__('business.extras.storefront_response_ar')" name="plumberResponseAr" wire:model.blur="plumberResponseAr" :liveValidate="false" />
            <div class="flex justify-end">
                <button type="submit" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:loading.attr="disabled">{{ __('business.save') }}</button>
            </div>
        </form>
    @endif
</div>
