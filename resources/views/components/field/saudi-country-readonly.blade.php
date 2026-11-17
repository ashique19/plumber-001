@props([
    'label' => null,
    'wireProp' => null,
])

@php
    use App\Support\Geo\SaudiNationalDefaults;

    $label ??= __('customers.fields.country_code');
    $display = SaudiNationalDefaults::countryDisplayLabel();
@endphp

<label class="form-field {{ $attributes->get('wrapper-class', '') }}">
    <span class="form-field__label type-label">{{ $label }}</span>
    <div class="admin-field-wrap">
        <input
            type="text"
            class="form-field__control bg-[var(--app-surface-muted)]/60"
            value="{{ $display }}"
            readonly
            tabindex="-1"
            data-testid="saudi-country-readonly"
        />
    </div>
    @if ($wireProp)
        <input type="hidden" wire:model="{{ $wireProp }}" value="{{ SaudiNationalDefaults::countryCode() }}" />
    @endif
</label>