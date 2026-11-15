@props(['label', 'name', 'hint' => null, 'required' => false, 'dir' => null, 'liveValidate' => true, 'wrapperClass' => ''])

@php
    $fieldInvalid = $errors->has($name);
@endphp
<label @class(['form-field', 'form-field--invalid' => $fieldInvalid, $wrapperClass])>
    <span class="form-field__label type-label">
        {{ $label }}
        @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif
    </span>
    @if($hint)
        <span class="form-field__hint type-small">{{ $hint }}</span>
    @endif
    <textarea
        name="{{ $name }}"
        id="{{ $name }}"
        @if($dir) dir="{{ $dir }}" @endif
        @if($liveValidate)
            wire:blur="validateLiveField('{{ $name }}')"
        @endif
        {{ $attributes->merge(['class' => 'form-field__control'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
    >{{ $slot }}</textarea>
    <x-field.partials.error :name="$name" />
</label>