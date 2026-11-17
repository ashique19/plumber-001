@props([
    'label',
    'name',
    'wireModel',
    'wireModelAr' => null,
    'required' => false,
])

@php
    use App\Support\Geo\SaudiCities;

    $options = SaudiCities::comboboxOptions();
@endphp

<div
    class="form-field saudi-city-combobox"
    data-testid="saudi-city-combobox"
    x-data="saudiCityCombobox({
        options: @js($options),
        otherKey: @js(SaudiCities::OTHER_KEY),
        wireProp: @js($wireModel),
        wirePropAr: @js($wireModelAr),
    })"
    x-on:click.outside="closePanel()"
>
    <span class="form-field__label type-label">
        {{ $label }}
        @if ($required)
            <span class="text-rose-600" aria-hidden="true">*</span>
        @endif
    </span>

    <template x-if="! isOther">
        <div class="saudi-city-combobox__search admin-field-wrap admin-field-wrap--with-icon">
            <x-app.icon name="search" class="admin-field-icon h-5 w-5" />
            <input
                type="search"
                name="{{ $name }}"
                id="{{ $name }}"
                class="form-field__control"
                autocomplete="off"
                x-model="query"
                x-on:focus="openPanel()"
                x-on:input="openPanel()"
                @keydown.enter.prevent
                placeholder="{{ __('saudi_cities.search_placeholder') }}"
                data-testid="saudi-city-combobox-search"
            />
        </div>
    </template>

    <template x-if="isOther">
        <div class="space-y-2">
            <p class="text-xs font-medium text-[var(--app-muted)]">{{ __('saudi_cities.other') }}</p>
            <input
                type="text"
                class="form-field__control"
                wire:model.blur="{{ $wireModel }}"
                x-on:input="onOtherInput($event)"
                placeholder="{{ __('saudi_cities.other_placeholder') }}"
                data-testid="saudi-city-combobox-other"
            />
            <button type="button" class="tap-action-muted text-xs font-semibold text-[var(--app-accent)]" x-on:click="isOther = false; syncFromWire(); openPanel()">
                {{ __('saudi_cities.choose_from_list') }}
            </button>
        </div>
    </template>

    <div
        class="saudi-city-combobox__panel"
        x-show="panelOpen && ! isOther"
        x-cloak
        role="listbox"
        data-testid="saudi-city-combobox-panel"
    >
        <template x-for="option in filteredOptions" :key="option.key">
            <button
                type="button"
                class="saudi-city-combobox__option"
                role="option"
                x-on:click="select(option)"
                x-text="option.label"
            ></button>
        </template>
    </div>

</div>