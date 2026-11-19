@props([
    'fields',
    'moreAddressProp',
    'saStandardBuyer' => false,
    'minimalIdentity' => false,
])

@php
    use App\Support\Customers\ZatcaPartyIdScheme;

    $legalAr = $fields['legal_name_ar'];
    $legalEn = $fields['legal_name_en'];
    $nameBlocks = app()->getLocale() === 'ar'
        ? [['prop' => $legalAr, 'key' => 'legal_name_ar'], ['prop' => $legalEn, 'key' => 'legal_name_en']]
        : [['prop' => $legalEn, 'key' => 'legal_name_en'], ['prop' => $legalAr, 'key' => 'legal_name_ar']];
    $countries = [
        'SA' => __('customers.country.SA'),
        'AE' => __('customers.country.AE'),
        'BH' => __('customers.country.BH'),
        'KW' => __('customers.country.KW'),
        'OM' => __('customers.country.OM'),
        'QA' => __('customers.country.QA'),
        'EG' => __('customers.country.EG'),
        'JO' => __('customers.country.JO'),
        'US' => __('customers.country.US'),
        'GB' => __('customers.country.GB'),
    ];
    $vatRegistered = (bool) ($this->{$fields['vat_registered']} ?? false);
    $country = strtoupper((string) ($this->{$fields['country_code']} ?? 'SA'));
    $showMore = $saStandardBuyer && $country === 'SA' ? true : (bool) ($this->{$moreAddressProp} ?? false);
    $saBuyer = $saStandardBuyer && $country === 'SA';
    $countryLabel = $countries[$country] ?? $country;
    $cityOptions = config('saudi_cities.options', ['Riyadh']);
    $currentCity = trim((string) ($this->{$fields['city']} ?? ''));
    $altType = strtoupper(trim((string) ($this->{$fields['alt_id_type']} ?? '')));
@endphp

<div class="customer-identity-fields customer-identity-fields--sectioned w-full min-w-0" data-testid="customer-identity-fields">
    <section class="customer-identity-fields__section" aria-labelledby="customer-identity-section-identity">
        <h3 id="customer-identity-section-identity" class="customer-identity-fields__heading">{{ __('customers.sections.identity') }}</h3>
        @if ($minimalIdentity || $saStandardBuyer)
            <p class="customer-identity-fields__hint m-0">{{ __('customers.legal_name_one_required_hint') }}</p>
        @endif
        <div class="customer-identity-fields__grid admin-modal-field-grid admin-modal-field-grid--2">
            @foreach ($nameBlocks as $block)
                <x-field.input
                    :label="__('customers.fields.'.$block['key'])"
                    :name="$block['prop']"
                    wire:model.blur="{{ $block['prop'] }}"
                    :dir="$block['key'] === 'legal_name_ar' ? 'rtl' : 'auto'"
                    :required="$minimalIdentity || $saStandardBuyer"
                />
            @endforeach

            <x-field.segmented
                :label="__('customers.fields.vat_registered')"
                name="{{ $fields['vat_registered'] }}"
                wire:model.live="{{ $fields['vat_registered'] }}"
                class="customer-identity-fields__vat customer-identity-fields__span-full"
                :options="[
                    '1' => __('customers.vat_status.registered'),
                    '0' => __('customers.vat_status.not_registered'),
                ]"
            />

            @if ($vatRegistered)
                <x-field.input
                    :label="__('customers.fields.vat_number')"
                    :name="$fields['vat_number']"
                    wire:model.blur="{{ $fields['vat_number'] }}"
                    inputmode="numeric"
                    required
                    wrapper-class="customer-identity-fields__span-full"
                />
            @else
                <x-field.select
                    :label="__('customers.fields.alt_id_type')"
                    :name="$fields['alt_id_type']"
                    wire:model.blur="{{ $fields['alt_id_type'] }}"
                >
                    <option value="">{{ __('customers.alt_id_type.choose') }}</option>
                    @foreach (ZatcaPartyIdScheme::options() as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </x-field.select>
                <x-field.input
                    :label="__('customers.fields.alt_id_number')"
                    :name="$fields['alt_id_number']"
                    wire:model.blur="{{ $fields['alt_id_number'] }}"
                    inputmode="{{ $altType === 'CRN' ? 'numeric' : 'text' }}"
                    :maxlength="$altType === 'CRN' ? 10 : null"
                />
            @endif
        </div>
    </section>

    <section class="customer-identity-fields__section" aria-labelledby="customer-identity-section-contact">
        <h3 id="customer-identity-section-contact" class="customer-identity-fields__heading">{{ __('customers.sections.contact') }}</h3>
        <div class="customer-identity-fields__grid admin-modal-field-grid admin-modal-field-grid--2">
            <x-field.input
                :label="__('customers.phone_optional')"
                :name="$fields['phone']"
                wire:model.blur="{{ $fields['phone'] }}"
                inputmode="tel"
                icon="phone"
            />
            <x-field.input
                type="email"
                :label="__('customers.email_optional')"
                :name="$fields['email']"
                wire:model.blur="{{ $fields['email'] }}"
                icon="mail"
            />
        </div>
    </section>

    <section class="customer-identity-fields__section" aria-labelledby="customer-identity-section-address">
        <h3 id="customer-identity-section-address" class="customer-identity-fields__heading">{{ __('customers.sections.national_address') }}</h3>
        @if ($saStandardBuyer)
            <p class="customer-identity-fields__hint">{{ __('invoices.standard_customer_modal_hint') }}</p>
        @endif
        <div class="customer-identity-fields__grid admin-modal-field-grid admin-modal-field-grid--2">
            @if ($saStandardBuyer)
                <div class="form-field min-w-0" data-testid="customer-country-readonly">
                    <span class="form-field__label type-label">{{ __('customers.fields.country_code') }}</span>
                    <div class="admin-field-wrap">
                        <p class="form-field__control m-0 flex min-h-12 items-center rounded-xl border border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 px-3 text-sm font-medium text-[var(--app-text)]" dir="auto">
                            {{ $countryLabel }}
                        </p>
                    </div>
                    <input type="hidden" name="{{ $fields['country_code'] }}" wire:model="{{ $fields['country_code'] }}" />
                </div>
            @else
                <x-field.select
                    :label="__('customers.fields.country_code')"
                    :name="$fields['country_code']"
                    wire:model.live="{{ $fields['country_code'] }}"
                >
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
                    @endforeach
                </x-field.select>
            @endif

            @if ($saBuyer)
                <x-field.select
                    :label="__('business.fields.city')"
                    :name="$fields['city']"
                    wire:model.blur="{{ $fields['city'] }}"
                    required
                >
                    @if ($currentCity !== '' && ! in_array($currentCity, $cityOptions, true))
                        <option value="{{ $currentCity }}">{{ $currentCity }}</option>
                    @endif
                    @foreach ($cityOptions as $cityOption)
                        <option value="{{ $cityOption }}">{{ $cityOption }}</option>
                    @endforeach
                </x-field.select>
            @else
                <x-field.input
                    :label="__('business.fields.city')"
                    :name="$fields['city']"
                    wire:model.blur="{{ $fields['city'] }}"
                />
            @endif

            @if ($saBuyer || $showMore)
                <x-field.input
                    :label="__('business.fields.district')"
                    :name="$fields['addr_district']"
                    wire:model.blur="{{ $fields['addr_district'] }}"
                    :required="$saBuyer"
                />
            @endif

            <x-field.textarea
                :label="__('business.fields.street')"
                :name="$fields['street_name']"
                wire:model.blur="{{ $fields['street_name'] }}"
                rows="2"
                :required="$saBuyer"
                wrapper-class="customer-identity-fields__span-full"
            />

            @if ($saBuyer || $showMore)
                <x-field.input
                    :label="__('business.fields.building')"
                    :name="$fields['addr_building']"
                    wire:model.blur="{{ $fields['addr_building'] }}"
                    :required="$saBuyer"
                />
                <x-field.input
                    :label="__('business.fields.postal')"
                    :name="$fields['addr_postal']"
                    wire:model.blur="{{ $fields['addr_postal'] }}"
                    inputmode="numeric"
                    maxlength="5"
                    :required="$saBuyer"
                />
                <x-field.input
                    :label="__('business.fields.additional')"
                    :name="$fields['addr_additional']"
                    wire:model.blur="{{ $fields['addr_additional'] }}"
                />
            @elseif (! $saBuyer)
                <div class="customer-identity-fields__span-full">
                    <button
                        type="button"
                        class="tap-action-outline min-h-11 px-4 text-sm font-semibold"
                        wire:click="$toggle('{{ $moreAddressProp }}')"
                    >
                        {{ __('customers.more_address_details') }}
                    </button>
                </div>
            @endif
        </div>
    </section>
</div>