@props([
    'show' => false,
    'title' => '',
    'closeWire' => null,
    'titleId' => null,
    'size' => 'md',
])

@php
    $titleId = $titleId ?? 'modal-title-'.md5($title.$closeWire);
    $sizeClass = match ($size) {
        'sm' => 'x-modal--sm',
        'lg' => 'x-modal--lg',
        default => 'x-modal--md',
    };
@endphp

@if ($show && filled($closeWire))
    <div
        class="x-modal-backdrop"
        wire:click.self="{{ $closeWire }}"
        x-data="adminModalBackdrop()"
        x-on:keydown.escape.window="$refs.modalClose?.click()"
    >
        <div
            {{ $attributes->class(['x-modal', $sizeClass]) }}
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $titleId }}"
            wire:click.stop
            x-trap.inert="true"
            x-init="$nextTick(() => $el.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled])')?.focus())"
        >
            <header class="x-modal__header">
                <h2 id="{{ $titleId }}" class="x-modal__title">{{ $title }}</h2>
                <button
                    type="button"
                    class="x-modal__close tap-action-muted"
                    wire:click="{{ $closeWire }}"
                    x-ref="modalClose"
                    aria-label="{{ __('catalog.cancel') }}"
                >
                    <x-app.icon name="x" class="h-5 w-5" />
                </button>
            </header>

            <div class="x-modal__body">
                {{ $slot }}
            </div>

            @isset($footer)
                <footer class="x-modal__footer">
                    {{ $footer }}
                </footer>
            @endisset
        </div>
    </div>
@endif