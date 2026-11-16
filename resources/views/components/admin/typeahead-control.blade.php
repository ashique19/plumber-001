@props([
    'label',
    'name',
    'placeholder' => '',
    'clearable' => false,
    'clearAction' => null,
    'addAction' => null,
    'showAdd' => false,
    'addLabel' => null,
    'resultsOpen' => false,
])

@php
    $hasAdd = $showAdd && filled($addAction);
@endphp

<div
    class="admin-typeahead"
    x-data="adminTypeaheadControl({ resultsOpen: @js($resultsOpen), addAction: @js($hasAdd ? $addAction : null) })"
    x-on:focusin="if ($el.contains($event.target)) { openPanel() }"
    x-on:click.outside="closePanel()"
>
    <span class="form-field__label type-label">{{ $label }}</span>
    <div @class(['admin-typeahead__control', 'admin-typeahead__control--with-add' => $hasAdd])>
        <div class="admin-typeahead__input-wrap admin-field-wrap admin-field-wrap--with-icon" @class(['admin-field-wrap--with-suffix' => $clearable])>
            <x-app.icon name="search" class="admin-field-icon h-5 w-5" />
            <input
                type="search"
                name="{{ $name }}"
                id="{{ $name }}"
                inputmode="search"
                autocomplete="off"
                placeholder="{{ $placeholder !== '' ? $placeholder : ' ' }}"
                data-admin-search-input="1"
                @keydown.enter.prevent
                x-on:input="openPanel()"
                x-on:paste="openPanel()"
                x-on:change="openPanel()"
                {{ $attributes->merge(['class' => 'form-field__control admin-typeahead__input']) }}
            />
            @if ($clearable && $clearAction)
                <button type="button" class="admin-field-clear tap-action-muted" wire:click="{{ $clearAction }}" aria-label="{{ __('payments.clear_statement') }}">
                    <x-app.icon name="x" class="h-5 w-5" />
                </button>
            @endif
        </div>
        @if ($hasAdd)
            <button
                type="button"
                class="admin-typeahead__add tap-action-muted"
                x-on:click.prevent.stop="invokeQuickAdd()"
                data-admin-quick-add="{{ $addAction }}"
                aria-label="{{ $addLabel ?? __('catalog.add') }}"
                title="{{ $addLabel ?? __('catalog.add') }}"
            >
                +
            </button>
        @endif
    </div>
    <div x-show="panelOpen" x-cloak>
        {{ $slot }}
    </div>
</div>