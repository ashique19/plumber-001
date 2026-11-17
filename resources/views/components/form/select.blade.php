@props(['label', 'name', 'hint' => null, 'required' => false, 'liveValidate' => true, 'wrapperClass' => ''])

@php
    $fieldInvalid = $errors->has($name);
@endphp
<label @class(['form-field', 'form-field--invalid' => $fieldInvalid, $wrapperClass])>
    <span class="form-field__label type-label">
        {{ html_entity_decode((string) $label, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}
        @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif
    </span>
    @if($hint)
        <span class="form-field__hint type-small">{{ $hint }}</span>
    @endif
    <div class="admin-field-wrap">
        <select
            name="{{ $name }}"
            id="{{ $name }}"
            @if($liveValidate)
                wire:change="touchLiveField('{{ $name }}'); validateLiveField('{{ $name }}')"
            @endif
            {{ $attributes->merge(['class' => 'form-field__control'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
        >
            {{ $slot }}
        </select>
    </div>
    <x-field.partials.error :name="$name" />
</label>