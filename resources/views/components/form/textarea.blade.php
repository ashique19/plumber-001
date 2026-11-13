@props(['label', 'name', 'hint' => null, 'rows' => 3, 'required' => false, 'liveValidate' => true])

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
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        rows="{{ $rows }}"
        @if($liveValidate)
            wire:blur="validateLiveField('{{ $name }}')"
        @endif
        {{ $attributes->merge(['class' => 'form-field__control font-mono text-xs'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
    >{{ $slot }}</textarea>
    @error($name)
        <span class="form-field__error" role="alert">{{ $message }}</span>
    @enderror
</label>
