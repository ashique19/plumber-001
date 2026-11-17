@props([
    'menuId',
    'summaryTestId' => 'hub-row-action-more',
    'summaryLabel' => null,
])

@php
    $summaryLabel ??= __('invoices.action_more');
@endphp

<div
    x-data="hubRowMoreMenu(@js($menuId))"
    class="hub-doc-table__more"
    wire:ignore.self
    data-hub-row-more-menu="{{ $menuId }}"
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click.prevent.stop="toggle()"
        x-bind:aria-expanded="isOpen"
        class="hub-doc-table__action hub-doc-table__action--icon hub-doc-table__action--more"
        aria-haspopup="menu"
        aria-label="{{ $summaryLabel }}"
        title="{{ $summaryLabel }}"
        data-testid="{{ $summaryTestId }}"
    >
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.75"/><circle cx="12" cy="12" r="1.75"/><circle cx="19" cy="12" r="1.75"/></svg>
        <span class="sr-only">{{ $summaryLabel }}</span>
    </button>
    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="isOpen"
            x-cloak
            x-on:click.outside="close()"
            x-on:click="onMenuItemActivate($event)"
            x-bind:style="panelStyle"
            x-bind:class="placement === 'top' ? 'hub-doc-table__more-panel--flip-up' : ''"
            class="hub-doc-table__more-panel hub-doc-table__more-panel--teleport"
            role="menu"
        >
            {{ $slot }}
        </div>
    </template>
</div>