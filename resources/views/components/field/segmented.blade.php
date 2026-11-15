@props([
    'label',
    'name',
    'options' => [],
])

<div {{ $attributes->except(['wire:model', 'wire:model.live', 'wire:model.blur', 'wire:model.defer'])->merge(['class' => 'form-field']) }}>
    <span class="form-field__label type-label">{{ $label }}</span>
    <div class="admin-segmented" role="radiogroup" aria-label="{{ $label }}">
        @foreach ($options as $value => $optionLabel)
            <label class="admin-segmented__option flex cursor-pointer items-center justify-center">
                <input
                    type="radio"
                    class="sr-only"
                    name="{{ $name }}"
                    value="{{ $value }}"
                    {{ $attributes->only(['wire:model', 'wire:model.live']) }}
                />
                <span>{{ $optionLabel }}</span>
            </label>
        @endforeach
    </div>
    <x-field.partials.error :name="$name" />
</div>