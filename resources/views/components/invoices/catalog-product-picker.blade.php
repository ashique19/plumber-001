@props([
    'lineIndex',
    'products' => [],
    'compact' => false,
    'canCreateProduct' => false,
])

<div
    class="invoice-catalog-picker"
    x-data="invoiceCatalogPicker({ lineIndex: @js($lineIndex) })"
    @keydown.escape.window="close()"
>
    <div class="mb-2 flex gap-2">
        <button
            type="button"
            @class([
                'tap-action-muted flex-1 px-3 text-sm font-semibold',
                'min-h-12' => $compact,
                'min-h-11' => ! $compact,
            ])
            @click="toggle()"
            :aria-expanded="open"
        >
            {{ __('invoices.pick_catalog_product') }}
        </button>
        @if ($canCreateProduct)
            <button
                type="button"
                class="invoice-picker-add tap-action-muted flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold sm:min-h-12"
                wire:click="openQuickProductModal({{ $lineIndex }})"
                aria-label="{{ __('invoices.add_product') }}"
                title="{{ __('invoices.add_product') }}"
            >
                +
            </button>
        @endif
    </div>

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            class="invoice-catalog-picker__backdrop app-sheet-backdrop sm:hidden"
            @click="close()"
            aria-hidden="true"
        ></div>
    </template>

    <div
        x-show="open"
        x-cloak
        x-transition
        class="invoice-catalog-picker__panel"
        role="dialog"
        aria-label="{{ __('invoices.pick_catalog_product') }}"
        @click.outside="close()"
    >
        <div class="invoice-catalog-picker__head">
            <p class="text-sm font-semibold">{{ __('invoices.pick_catalog_product') }}</p>
            <button type="button" class="tap-action-muted min-h-10 min-w-10 rounded-xl" @click="close()" aria-label="{{ __('catalog.cancel') }}">
                <x-app.icon name="x" class="h-5 w-5" />
            </button>
        </div>
        <input
            type="search"
            class="form-field__control w-full min-h-11"
            wire:model.live.debounce.350ms="catalogLineSearch.{{ $lineIndex }}"
            placeholder="{{ __('invoices.search_catalog_products') }}"
            autocomplete="off"
        />
        <ul class="invoice-catalog-picker__list" role="listbox">
            @forelse ($products as $product)
                <li wire:key="catalog-line-{{ $lineIndex }}-{{ $product['id'] }}">
                    <button
                        type="button"
                        class="invoice-catalog-picker__option tap-action-muted"
                        @click="pick({{ $product['id'] }})"
                        role="option"
                    >
                        <span class="font-medium">{{ $product['label'] }}</span>
                        <span class="text-xs text-[var(--app-muted)]">{{ $product['meta'] }}</span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-4 text-center text-sm text-[var(--app-muted)]">
                    {{ __('invoices.catalog_no_match') }}
                </li>
            @endforelse
        </ul>
    </div>
</div>