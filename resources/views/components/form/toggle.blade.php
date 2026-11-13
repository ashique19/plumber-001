@props(['label', 'name', 'hint' => null])

<label class="form-toggle {{ $attributes->get('class') }}">
    <input
        type="checkbox"
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->except('class')->merge(['class' => 'form-toggle__input']) }}
    />
    <span class="form-toggle__copy">
        <span class="form-toggle__label">{{ $label }}</span>
        @if($hint)
            <span class="form-toggle__hint">{{ $hint }}</span>
        @endif
    </span>
</label>
