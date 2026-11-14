@props([
    'title',
    'closeWire',
    'formId' => null,
    'submitLabel' => null,
    'submitTarget' => null,
    'cancelLabel' => null,
    'wide' => false,
    'narrow' => false,
    'showFooter' => true,
])

@php
    $submitLabel ??= __('catalog.save');
    $cancelLabel ??= __('catalog.cancel');
    $titleId = 'admin-sheet-title-'.md5($title.$closeWire);
@endphp

<div
    {{ $attributes->class([
        'app-sheet app-sheet--dialog app-sheet--admin',
        'app-sheet--wide' => $wide,
        'app-sheet--narrow' => $narrow,
    ]) }}
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $titleId }}"
>
    <div class="app-sheet__head">
        <h2 id="{{ $titleId }}">{{ $title }}</h2>
        <button type="button" class="app-sheet__close tap-action-muted" wire:click="{{ $closeWire }}" aria-label="{{ $cancelLabel }}">
            <x-app.icon name="x" class="h-5 w-5" />
        </button>
    </div>

    <div class="app-sheet__body">
        {{ $slot }}
    </div>

    @if ($showFooter)
        <footer class="app-sheet__footer">
            @isset($footer)
                {{ $footer }}
            @else
                <button type="button" class="tap-action-muted min-h-11 flex-1 text-sm font-semibold" wire:click="{{ $closeWire }}">
                    {{ $cancelLabel }}
                </button>
                @if ($formId)
                    <x-ui.livewire-action
                        type="submit"
                        :target="$submitTarget ?? 'save'"
                        :form="$formId"
                        loading="saving"
                        class="tap-action-primary min-h-11 flex-1 text-sm font-semibold"
                        variant="primary"
                    >
                        {{ $submitLabel }}
                    </x-ui.livewire-action>
                @endif
            @endisset
        </footer>
    @endif
</div>