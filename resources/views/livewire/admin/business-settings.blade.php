@php
    use App\Support\EnabledLocales;
@endphp
<div class="business-settings-page pb-24 lg:pb-8 min-w-0 max-w-full" dir="{{ EnabledLocales::direction(app()->getLocale()) }}" x-data="{ sectionTab: @entangle('section').live }">
    <div class="business-settings-page__sticky-head">
        <header class="min-w-0">
            <h1 class="app-page-title break-words">{{ __('business.title') }}</h1>
            <p class="app-page-lead break-words">{{ __('business.subtitle') }}</p>
        </header>

        <nav class="app-section-tabs app-section-tabs--scroll business-settings-page__tabs mt-4 flex min-w-0 w-full" aria-label="{{ __('business.title') }}">
            @php
                $businessTabKeys = ['brand', 'legal', 'invoicing', 'contact', 'hours', 'branches', 'extras'];
            @endphp
            @foreach ($businessTabKeys as $key)
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
    </div>

    <div class="business-settings-page__panels min-w-0">
    @if ($errors->isNotEmpty())
        <div class="app-card mb-4 border-amber-200 bg-amber-50 p-4 text-sm text-amber-950" role="alert">
            <p class="font-semibold">{{ __('business.validation.summary_title') }}</p>
            <ul class="mt-2 list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($section === 'brand')
        <form class="app-card flex flex-col gap-4 p-4" novalidate wire:keydown.enter.prevent>
            <x-field.input :label="__('business.brand.display_name_en')" name="displayNameEn" wire:model.live="displayNameEn" :liveValidate="false" />
            <x-field.input :label="__('business.brand.display_name_ar')" name="displayNameAr" wire:model.live="displayNameAr" :liveValidate="false" />
            <x-field.input :label="__('business.brand.display_name_bn')" name="displayNameBn" wire:model.live="displayNameBn" :liveValidate="false" />
            <x-field.input :label="__('business.brand.tagline_en')" name="taglineEn" wire:model.live="taglineEn" :liveValidate="false" />
            <x-field.input :label="__('business.brand.tagline_ar')" name="taglineAr" wire:model.live="taglineAr" :liveValidate="false" />
            <x-field.select :label="__('business.brand.brand_color')" name="brandColorKey" wire:model="brandColorKey" :liveValidate="false">
                @foreach ($colorOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </x-field.select>
            <x-form.logo-upload
                :label="__('business.brand.logo')"
                wire-model="logoUpload"
                :preview-url="$currentLogo"
                :awaiting-save="$logoAwaitingSave"
                remove-action="removeLogo"
            />
            <div class="flex justify-end">
                <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveBrand" wire:loading.attr="disabled" wire:target="saveBrand">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'legal')
        <form class="app-card flex flex-col gap-4 p-4" novalidate wire:keydown.enter.prevent>
            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">{{ __('business.legal.zheart_note') }}</p>
            <p class="text-xs text-[var(--app-muted)]">{{ __('business.legal.optional_hint') }}</p>
            <x-field.input :label="__('business.legal.name_en')" name="legalNameEn" wire:model.live="legalNameEn" :liveValidate="false" />
            <x-field.input :label="__('business.legal.name_ar')" name="legalNameAr" wire:model.live="legalNameAr" :liveValidate="false" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field.input :label="__('business.legal.vat')" name="vatNumber" wire:model.live="vatNumber" :liveValidate="false" />
                <x-field.input :label="__('business.legal.cr')" name="crNumber" wire:model.live="crNumber" :liveValidate="false" />
                <x-field.input :label="__('invoices.unified_number')" name="unifiedNumber" wire:model.live="unifiedNumber" :liveValidate="false" />
                <x-field.input :label="__('business.legal.email')" name="legalEmail" wire:model.live="legalEmail" :liveValidate="false" />
                <x-field.input :label="__('business.legal.mobile')" name="legalMobile" wire:model.live="legalMobile" :liveValidate="false" />
                <x-field.input :label="__('business.legal.mobile_secondary')" name="legalMobileSecondary" wire:model.live="legalMobileSecondary" :liveValidate="false" />
            </div>
            <x-field.input :label="__('business.legal.short_national')" name="shortNationalAddress" wire:model.live="shortNationalAddress" :liveValidate="false" />
            <x-field.input :label="__('business.legal.address_en')" name="addressEn" wire:model.live="addressEn" :liveValidate="false" />
            <x-field.input :label="__('business.legal.address_ar')" name="addressAr" wire:model.live="addressAr" :liveValidate="false" dir="rtl" />
            <h3 class="form-section__title">{{ __('business.legal.national_address') }}</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-field.input :label="__('business.legal.na_building')" name="naBuilding" wire:model.live="naBuilding" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_street')" name="naStreet" wire:model.live="naStreet" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_street_ar')" name="naStreetAr" wire:model.live="naStreetAr" :liveValidate="false" dir="rtl" />
                <x-field.input :label="__('business.legal.na_additional')" name="naAdditional" wire:model.live="naAdditional" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_district')" name="naDistrict" wire:model.live="naDistrict" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_district_ar')" name="naDistrictAr" wire:model.live="naDistrictAr" :liveValidate="false" dir="rtl" />
                <x-field.input :label="__('business.legal.na_city')" name="naCity" wire:model.live="naCity" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_city_ar')" name="naCityAr" wire:model.live="naCityAr" :liveValidate="false" dir="rtl" />
                <x-field.input :label="__('business.legal.na_postal')" name="naPostal" wire:model.live="naPostal" :liveValidate="false" />
                <x-field.input :label="__('business.legal.na_country')" name="naCountry" wire:model.live="naCountry" :liveValidate="false" maxlength="2" />
            </div>
            <p class="text-xs text-[var(--app-muted)]">{{ __('business.legal.logo_on_brand') }}</p>
            <div class="flex justify-end">
                <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveLegal" wire:loading.attr="disabled" wire:target="saveLegal">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'invoicing')
        <div class="flex flex-col gap-4">
            <form class="app-card flex flex-col gap-4 p-4" novalidate wire:keydown.enter.prevent>
                <p class="text-sm leading-snug text-[var(--app-muted)]">{{ __('business.invoicing.lead') }}</p>
                <p class="text-xs text-[var(--app-muted)]">
                    {{ config('zheart.auto_submit_signed') ? __('business.invoicing.server_auto_submit_on') : __('business.invoicing.server_auto_submit_off') }}
                </p>
                <x-field.input type="number" min="0" step="1" :label="__('invoices.payment_terms_days')" name="paymentTermsDays" wire:model.live="paymentTermsDays" />
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label class="mb-1 block text-sm font-medium text-[var(--app-fg)]" for="invoicePrintTemplate">{{ __('business.print.invoice_template') }}</label>
                        <select id="invoicePrintTemplate" class="min-h-11 w-full rounded-lg border border-[var(--app-border)] px-3 text-sm" wire:model="invoicePrintTemplate">
                            @foreach (\App\Enums\PrintDocumentTemplate::cases() as $tpl)
                                <option value="{{ $tpl->value }}">{{ $tpl->label() }}</option>
                            @endforeach
                        </select>
                        <a class="mt-1 inline-block text-xs text-[var(--app-accent)] underline" href="{{ route('admin.print.template-preview', ['kind' => 'invoice', 'template' => $invoicePrintTemplate]) }}" target="_blank" rel="noopener">{{ __('business.print.preview') }}</a>
                    </div>
                    <div class="min-w-0">
                        <label class="mb-1 block text-sm font-medium text-[var(--app-fg)]" for="quotationPrintTemplate">{{ __('business.print.quotation_template') }}</label>
                        <select id="quotationPrintTemplate" class="min-h-11 w-full rounded-lg border border-[var(--app-border)] px-3 text-sm" wire:model="quotationPrintTemplate">
                            @foreach (\App\Enums\PrintDocumentTemplate::cases() as $tpl)
                                <option value="{{ $tpl->value }}">{{ $tpl->label() }}</option>
                            @endforeach
                        </select>
                        <a class="mt-1 inline-block text-xs text-[var(--app-accent)] underline" href="{{ route('admin.print.template-preview', ['kind' => 'quotation', 'template' => $quotationPrintTemplate]) }}" target="_blank" rel="noopener">{{ __('business.print.preview') }}</a>
                    </div>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" class="mt-1 rounded border-gray-300" wire:model="invoicingIntegrationEnabled" />
                    <span>
                        <span class="font-medium text-[var(--app-fg)]">{{ __('business.invoicing.integration_enabled') }}</span>
                        <span class="mt-1 block text-xs text-[var(--app-muted)]">{{ __('business.invoicing.integration_hint') }}</span>
                    </span>
                </label>
                <div class="flex justify-stretch sm:justify-end">
                    <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveInvoicing" wire:loading.attr="disabled" wire:target="saveInvoicing">{{ __('business.save') }}</button>
                </div>
            </form>

            @if ($isOwner)
                <form class="app-card flex flex-col gap-4 p-4" novalidate wire:keydown.enter.prevent data-testid="business-zheart-form">
                    <h2 class="text-sm font-semibold text-[var(--app-fg)]">{{ __('business.zheart.connection_heading') }}</h2>
                    <p class="text-xs leading-snug text-[var(--app-muted)]">{{ __('business.zheart.lead') }}</p>
                    <div class="space-y-1">
                        <x-field.input
                            type="url"
                            inputmode="url"
                            autocomplete="off"
                            :label="__('business.zheart.base_url')"
                            name="zheartBaseUrl"
                            wire:model.live="zheartBaseUrl"
                            :liveValidate="false"
                            placeholder="https://z-heart.example.com"
                        />
                        @if ($zheartBaseUrlFromEnv)
                            <p class="text-xs text-[var(--app-muted)]" data-testid="business-zheart-base-env-hint">{{ __('business.zheart.base_url_from_env') }}</p>
                        @endif
                    </div>
                    <div class="space-y-2" data-testid="business-zheart-api-key">
                        @if ($zheartHasStoredKey && ! $zheartReplaceKeyOpen)
                            <p class="text-sm text-[var(--app-fg)]" dir="ltr" data-testid="business-zheart-key-status">
                                {{ __('business.zheart.key_status_saved', ['last4' => $zheartKeyLast4 ?? '????']) }}
                            </p>
                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                                <button type="button" class="tap-action-muted min-h-10 w-full px-3 text-sm sm:w-auto" wire:click="beginZheartReplaceKey">{{ __('business.zheart.replace_key') }}</button>
                                <button type="button" class="tap-action-muted min-h-10 w-full px-3 text-sm text-red-700 sm:w-auto" wire:click="removeZheartKey" wire:confirm="{{ __('business.zheart.remove_confirm') }}">{{ __('business.zheart.remove_key') }}</button>
                            </div>
                        @elseif ($zheartUsingEnvFallback && ! $zheartReplaceKeyOpen)
                            <p class="text-sm text-[var(--app-fg)]" dir="ltr" data-testid="business-zheart-key-status">
                                {{ __('business.zheart.key_status_env', ['last4' => $zheartKeyLast4 ?? '????']) }}
                            </p>
                            <button type="button" class="tap-action-muted min-h-10 w-full px-3 text-sm sm:w-auto" wire:click="beginZheartReplaceKey">{{ __('business.zheart.store_key_in_db') }}</button>
                        @else
                            <x-field.input
                                type="password"
                                autocomplete="new-password"
                                :label="$zheartHasStoredKey ? __('business.zheart.new_api_key') : __('business.zheart.api_key')"
                                name="zheartApiKeyInput"
                                wire:model="zheartApiKeyInput"
                                :liveValidate="false"
                            />
                        @endif
                    </div>
                    @include('livewire.admin.partials.zheart-test-result')
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                        <button type="button" class="tap-action-muted min-h-11 w-full sm:w-auto" wire:click="testZheartConnection" wire:loading.attr="disabled" wire:target="testZheartConnection">{{ __('business.zheart.test_connection') }}</button>
                        <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveZheart" wire:loading.attr="disabled" wire:target="saveZheart">{{ __('business.save') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @elseif ($section === 'contact')
        <form class="app-card flex flex-col gap-6 p-4" novalidate wire:keydown.enter.prevent>
            <fieldset class="flex flex-col gap-4 border-0 p-0">
                <legend class="form-section__title mb-1 w-full">{{ __('business.contact.section_phone') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field.input :label="__('business.contact.phone')" name="phone" wire:model.live="phone" :liveValidate="false" />
                    <x-field.input :label="__('business.contact.whatsapp')" name="whatsapp" wire:model.live="whatsapp" :liveValidate="false" />
                    <x-field.input :label="__('business.contact.email')" name="email" type="email" wire:model.live="email" :liveValidate="true" wrapperClass="sm:col-span-2" />
                    <x-field.input :label="__('business.contact.website')" name="website" wire:model.live="website" :liveValidate="false" wrapperClass="sm:col-span-2" />
                </div>
            </fieldset>
            <fieldset class="flex flex-col gap-4 border-0 p-0">
                <legend class="form-section__title mb-1 w-full">{{ __('business.contact.section_social') }}</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field.input :label="__('business.contact.instagram')" name="instagram" wire:model.live="instagram" :liveValidate="false" />
                    <x-field.input :label="__('business.contact.x')" name="socialX" wire:model.live="socialX" :liveValidate="false" />
                    <x-field.input :label="__('business.contact.tiktok')" name="tiktok" wire:model.live="tiktok" :liveValidate="false" />
                    <x-field.input :label="__('business.contact.snapchat')" name="snapchat" wire:model.live="snapchat" :liveValidate="false" />
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
                                <x-field.input :label="__('business.contact.lat')" name="lat" wire:model.live="lat" :liveValidate="false" />
                                <x-field.input :label="__('business.contact.lng')" name="lng" wire:model.live="lng" :liveValidate="false" />
                                <x-field.input :label="__('business.contact.maps_url')" name="mapsUrl" wire:model.live="mapsUrl" :liveValidate="false" wrapperClass="sm:col-span-2" />
                            </div>
                        </div>
                    </x-slot>
                </x-form.location-picker>
            </fieldset>
            <div class="flex justify-end">
                <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveContact" wire:loading.attr="disabled" wire:target="saveContact">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'hours')
        <form class="app-card flex flex-col gap-4 p-4 min-w-0 max-w-full" novalidate wire:keydown.enter.prevent>
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
            <x-field.input :label="__('business.hours.note_field')" name="hoursNote" wire:model.live="hoursNote" :liveValidate="false" />
            <div class="flex justify-end">
                <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveHours" wire:loading.attr="disabled" wire:target="saveHours">{{ __('business.save') }}</button>
            </div>
        </form>
    @elseif ($section === 'branches')
        <section class="app-card flex flex-col gap-4 p-4">
            <p class="text-sm text-[var(--app-muted)]">{{ __('business.branches.intro') }}</p>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if ($usersUrl ?? null)
                    <a href="{{ $usersUrl }}" class="tap-action-primary min-h-11 w-full text-center sm:w-auto">{{ __('business.branches.open_users') }}</a>
                @endif
                <a href="{{ $teamUrl }}" class="tap-action-muted min-h-11 w-full text-center sm:w-auto">{{ __('business.branches.open_team') }}</a>
            </div>
        </section>
    @elseif ($section === 'extras')
        <form class="app-card flex flex-col gap-4 p-4" novalidate wire:keydown.enter.prevent>
            <x-field.toggle :label="__('business.extras.storefront_emergency')" name="plumberEmergency" wire:model="plumberEmergency" />
            <x-field.input :label="__('business.extras.storefront_license')" name="plumberLicense" wire:model.live="plumberLicense" :liveValidate="false" />
            <x-field.textarea :label="__('business.extras.storefront_areas')" name="plumberServiceAreas" wire:model.live="plumberServiceAreas" rows="3" :liveValidate="false" />
            <x-field.input :label="__('business.extras.storefront_response_en')" name="plumberResponseEn" wire:model.live="plumberResponseEn" :liveValidate="false" />
            <x-field.input :label="__('business.extras.storefront_response_ar')" name="plumberResponseAr" wire:model.live="plumberResponseAr" :liveValidate="false" />
            <div class="flex justify-end">
                <button type="button" class="tap-action-primary min-h-11 w-full sm:w-auto" wire:click="saveExtras" wire:loading.attr="disabled" wire:target="saveExtras">{{ __('business.save') }}</button>
            </div>
        </form>
    @endif
    </div>
</div>
