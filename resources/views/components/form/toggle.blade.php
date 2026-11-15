@props(['label', 'name', 'hint' => null])

<label class="form-toggle">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge(['class' => 'form-toggle__input']) }}
    />
    <span class="form-toggle__track" aria-hidden="true">
        <span class="form-toggle__thumb"></span>
    </span>
    <span class="form-toggle__copy">
        <span class="form-toggle__label type-label">{{ $label }}</span>
        @if($hint)
            <span class="form-toggle__hint type-small">{{ $hint }}</span>
        @endif
    </span>
</label>