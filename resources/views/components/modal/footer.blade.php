@props([
    'cancelWire' => null,
    'cancelLabel' => null,
    'submitLabel' => null,
    'submitTarget' => null,
    'submitType' => 'submit',
    'submitWire' => null,
    'submitLoading' => 'saving',
    'submitClass' => 'tap-action-primary min-h-12 min-w-[11.5rem] px-5 text-sm font-semibold',
    'cancelClass' => 'tap-action-muted min-h-12 px-5 text-sm font-semibold',
])

@php
    $cancelLabel = $cancelLabel ?? __('catalog.cancel');
    $submitLabel = $submitLabel ?? __('catalog.save_and_select');
    $actionTarget = $submitTarget ?? $submitWire;
@endphp

<div {{ $attributes->class(['x-modal-footer-actions']) }}>
    @if ($cancelWire)
        <button type="button" class="{{ $cancelClass }}" wire:click="{{ $cancelWire }}" wire:loading.attr="disabled" @if ($actionTarget) wire:target="{{ $actionTarget }}" @endif>
            {{ $cancelLabel }}
        </button>
    @endif

    @if ($actionTarget)
        <x-ui.livewire-action
            :type="$submitWire ? 'button' : $submitType"
            :wire-click="$submitWire"
            :target="$actionTarget"
            :loading="$submitLoading"
            class="{{ $submitClass }}"
            variant="primary"
        >
            {{ $submitLabel }}
        </x-ui.livewire-action>
    @else
        {{ $slot }}
    @endif
</div>