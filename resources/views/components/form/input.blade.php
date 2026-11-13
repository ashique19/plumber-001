@props([
    'label',
    'name',
    'type' => 'text',
    'hint' => null,
    'required' => false,
    'dir' => null,
    'inputmode' => null,
    'maxlength' => null,
    'pattern' => null,
    'autocomplete' => 'off',
    'wrapperClass' => '',
    'liveValidate' => true,
])

@php
    $fieldInvalid = $errors->has($name);
@endphp
<label @class(['form-field', 'form-field--invalid' => $fieldInvalid, $wrapperClass])>
    <span class="form-field__label">
        {{ $label }}
        @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif
    </span>
    @if($hint)
        <span class="form-field__hint">{{ $hint }}</span>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        @if($dir) dir="{{ $dir }}" @endif
        @if($inputmode) inputmode="{{ $inputmode }}" @endif
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
        @if($pattern) pattern="{{ $pattern }}" @endif
        autocomplete="{{ $autocomplete }}"
        @if($liveValidate)
            wire:blur="validateLiveField('{{ $name }}')"
        @endif
        {{ $attributes->merge(['class' => 'form-field__control'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
    />
    @error($name)
        <span class="form-field__error" role="alert">{{ $message }}</span>
    @enderror
</label>
