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
    'liveValidate' => null,
    'icon' => null,
    'prefix' => null,
    'clearable' => false,
    'clearAction' => null,
])

@php
    $liveValidate = $liveValidate ?? ! in_array($type, ['search'], true);
    $fieldInvalid = $errors->has($name);
    $wrapClass = 'admin-field-wrap';
    if ($icon) {
        $wrapClass .= ' admin-field-wrap--with-icon';
    }
    if ($prefix) {
        $wrapClass .= ' admin-field-wrap--with-prefix';
    }
    if ($clearable) {
        $wrapClass .= ' admin-field-wrap--with-suffix';
    }
@endphp
<label @class(['form-field', 'form-field--invalid' => $fieldInvalid, $wrapperClass])>
    <span class="form-field__label type-label">
        {{ $label }}
        @if($required)<span class="text-rose-600" aria-hidden="true">*</span>@endif
    </span>
    @if($hint)
        <span class="form-field__hint type-small">{{ $hint }}</span>
    @endif
    <div @class([$wrapClass])>
        @if($icon)
            <x-app.icon :name="$icon" class="admin-field-icon h-5 w-5" />
        @endif
        @if($prefix)
            <span class="admin-field-prefix">{{ $prefix }}</span>
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
            placeholder="{{ $attributes->get('placeholder') ?? ' ' }}"
            @if ($icon === 'search' || $type === 'search')
                data-admin-search-input="1"
            @endif
            @if ($liveValidate)
                wire:blur="validateLiveField('{{ $name }}')"
            @endif
            {{ $attributes->except('placeholder')->merge(['class' => 'form-field__control'.($fieldInvalid ? ' form-field__control--invalid' : '')]) }}
        />
        @if($clearable && $clearAction)
            <button type="button" class="admin-field-clear tap-action-muted" wire:click="{{ $clearAction }}" aria-label="{{ __('payments.clear_statement') }}">
                <x-app.icon name="x" class="h-5 w-5" />
            </button>
        @endif
    </div>
    <x-field.partials.error :name="$name" />
</label>