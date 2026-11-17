@props([
    'fields',
    'moreAddressProp',
    'saStandardBuyer' => false,
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
    $showMore = (bool) ($this->{$moreAddressProp} ?? false);
@endphp

<div class="customer-identity-fields admin-modal-field-grid admin-modal-field-grid--2 col-span-full w-full min-w-0" data-testid="customer-identity-fields">
    @foreach ($nameBlocks as $block)
        <x-field.input
            :label="__('customers.fields.'.$block['key'])"
            :name="$block['prop']"
            wire:model.blur="{{ $block['prop'] }}"
            :dir="$block['key'] === 'legal_name_ar' ? 'rtl' : 'auto'"
            wrapper-class="md:col-span-2"
        />
    @endforeach

    <x-field.segmented
        :label="__('customers.fields.vat_registered')"
        name="{{ $fields['vat_registered'] }}"
        wire:model.live="{{ $fields['vat_registered'] }}"
        class="md:col-span-2"
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
            wrapper-class="md:col-span-2"
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
        />
    @endif

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

    <x-field.select
        :label="__('customers.fields.country_code')"
        :name="$fields['country_code']"
        wire:model.live="{{ $fields['country_code'] }}"
        wrapper-class="md:col-span-2"
    >
        @foreach ($countries as $code => $label)
            <option value="{{ $code }}">{{ $code }} — {{ $label }}</option>
        @endforeach
    </x-field.select>

    <x-field.input
        :label="__('business.fields.street')"
        :name="$fields['street_name']"
        wire:model.blur="{{ $fields['street_name'] }}"
        wrapper-class="md:col-span-2"
    />
    <x-field.input
        :label="__('business.fields.city')"
        :name="$fields['city']"
        wire:model.blur="{{ $fields['city'] }}"
    />

    <div class="md:col-span-2">
        <button
            type="button"
            class="tap-action-muted text-xs font-semibold text-[var(--app-accent)]"
            wire:click="$toggle('{{ $moreAddressProp }}')"
        >
            {{ __('customers.more_address_details') }}
        </button>
    </div>

    @if ($showMore)
        <x-field.input
            :label="__('business.fields.building')"
            :name="$fields['addr_building']"
            wire:model.blur="{{ $fields['addr_building'] }}"
            :required="$saStandardBuyer && $country === 'SA'"
        />
        <x-field.input
            :label="__('business.fields.postal')"
            :name="$fields['addr_postal']"
            wire:model.blur="{{ $fields['addr_postal'] }}"
            :required="$saStandardBuyer && $country === 'SA'"
        />
        <x-field.input
            :label="__('business.fields.district')"
            :name="$fields['addr_district']"
            wire:model.blur="{{ $fields['addr_district'] }}"
        />
        <x-field.input
            :label="__('business.fields.additional')"
            :name="$fields['addr_additional']"
            wire:model.blur="{{ $fields['addr_additional'] }}"
            wrapper-class="md:col-span-2"
        />
    @endif
</div>