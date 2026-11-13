{{-- For list rows, pass the full Livewire action target, e.g. target="refund(12)" or :target="'deleteProduct('.$id.')'" --}}
@props([
    'target',
    'type' => 'button',
    'variant' => 'primary',
    'loading' => 'generic',
    'disabled' => false,
])

@php
    $variantClass = $variant === 'primary' ? 'tap-action-primary' : 'tap-action-muted';
    $loadingLabel = __('loading.'.$loading);
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $variantClass.' inline-flex items-center justify-center gap-2'])->except('type') }}
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
    @disabled($disabled)
>
    <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
    <span wire:loading wire:target="{{ $target }}" class="inline-flex items-center justify-center gap-2">
        <x-ui.spinner />
        <span>{{ $loadingLabel }}</span>
    </span>
</button>
