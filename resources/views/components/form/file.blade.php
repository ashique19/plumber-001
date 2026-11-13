@props([
    'label',
    'name',
    'hint' => null,
    'previewUrl' => null,
    'removeAction' => null,
    'chooseLabel' => null,
    'removeLabel' => null,
])

@php
    $chooseLabel ??= __('business.brand.logo_choose');
    $removeLabel ??= __('business.brand.logo_remove');
@endphp

<div {{ $attributes->class(['form-field']) }}>
    <span class="form-field__label">{{ $label }}</span>
    @if ($hint)
        <span class="form-field__hint">{{ $hint }}</span>
    @endif
    <div class="form-file-upload">
        @if ($previewUrl)
            <div class="form-file-upload__preview">
                <img src="{{ asset($previewUrl) }}" alt="" class="form-file-upload__thumb" width="64" height="64" loading="lazy">
                @if ($removeAction)
                    <button type="button" class="tap-action-muted text-xs font-semibold" wire:click="{{ $removeAction }}">
                        {{ $removeLabel }}
                    </button>
                @endif
            </div>
        @endif
        <label class="form-file-upload__trigger tap-action-muted">
            <x-app.icon name="paint" class="h-4 w-4 shrink-0 opacity-80" />
            <span class="min-w-0 truncate">{{ $chooseLabel }}</span>
            <input
                type="file"
                name="{{ $name }}"
                id="{{ $name }}"
                {{ $attributes->except(['class', 'label', 'hint', 'previewUrl', 'removeAction', 'chooseLabel', 'removeLabel'])->merge(['class' => 'sr-only']) }}
            />
        </label>
        <p class="form-file-upload__status" wire:loading wire:target="{{ $name }}">{{ __('loading.generic') }}</p>
    </div>
    @error($name)
        <span class="form-field__error" role="alert">{{ $message }}</span>
    @enderror
</div>
