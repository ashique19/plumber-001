@props([
    'cancelWire' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
    'submitTarget' => null,
    'submitType' => 'submit',
    'submitWire' => null,
    'submitClass' => 'tap-action-primary min-h-12 px-5 text-sm font-semibold',
    'cancelClass' => 'tap-action-muted min-h-12 px-5 text-sm font-semibold',
])

@php
    $cancelLabel = $cancelLabel ?? __('catalog.cancel');
    $submitLabel = $submitLabel ?? __('catalog.save_and_select');
@endphp

<div {{ $attributes->class(['x-modal-footer-actions']) }}>
    @if ($cancelWire)
        <button type="button" class="{{ $cancelClass }}" wire:click="{{ $cancelWire }}">
            {{ $cancelLabel }}
        </button>
    @endif

    @if ($submitWire)
        <x-ui.livewire-action
            type="button"
            wire:click="{{ $submitWire }}"
            :target="$submitTarget"
            :loading="$submitTarget"
            class="{{ $submitClass }}"
            variant="primary"
        >
            {{ $submitLabel }}
        </x-ui.livewire-action>
    @elseif ($submitTarget)
        <x-ui.livewire-action
            :type="$submitType"
            :target="$submitTarget"
            :loading="$submitTarget"
            class="{{ $submitClass }}"
            variant="primary"
        >
            {{ $submitLabel }}
        </x-ui.livewire-action>
    @else
        {{ $slot }}
    @endif
</div>