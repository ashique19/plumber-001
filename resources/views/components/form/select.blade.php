@props(['label', 'name', 'hint' => null, 'required' => false, 'liveValidate' => true])

@php
    $fieldInvalid = $errors->has($name);
@endphp
<label @class(['form-field', 'form-field--invalid' => $fieldInvalid])>
    <span class="form-field__label">
        {{ $label }}
        @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif
    </span>
    @if($hint)
        <span class="form-field__hint">{{ $hint }}</span>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        @if($liveValidate)
            wire:change="validateLiveField('{{ $name }}')"
        @endif
        {{ $attributes->merge(['class' => 'form-field__control'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
    >
        {{ $slot }}
    </select>
    @error($name)
        <span class="form-field__error" role="alert">{{ $message }}</span>
    @enderror
</label>
