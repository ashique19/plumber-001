{{-- For list rows, pass the full Livewire action target, e.g. target="refund(12)" or :target="'deleteProduct('.$id.')'" --}}
@props([
    'target',
    'type' => 'button',
    'variant' => 'primary',
    'loading' => null,
    'disabled' => false,
    'wireClick' => null,
    'form' => null,
])

@php
    $variantClass = $variant === 'primary' ? 'tap-action-primary' : 'tap-action-muted';
    $loadingKey = filled($loading) ? (string) $loading : (filled($target) ? (string) $target : 'generic');
    $loadingLabel = \Illuminate\Support\Facades\Lang::has('loading.'.$loadingKey)
        ? __('loading.'.$loadingKey)
        : __('loading.saving');
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => $variantClass.' x-livewire-action inline-flex items-center justify-center gap-2'])->except('type') }}
    @if ($wireClick) wire:click="{{ $wireClick }}" @endif
    @if ($form) form="{{ $form }}" @endif
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
    @disabled($disabled)
>
    <span wire:loading.remove wire:target="{{ $target }}" class="x-livewire-action__label">{{ $slot }}</span>
    <span wire:loading wire:target="{{ $target }}" class="x-livewire-action__loading inline-flex items-center justify-center gap-2">
        <x-ui.spinner />
        <span>{{ $loadingLabel }}</span>
    </span>
</button>
