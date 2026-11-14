@props(['lineIndex', 'products'])

<div
    class="invoice-catalog-picker"
    x-data="invoiceCatalogPicker({ lineIndex: @js($lineIndex), products: @js($products) })"
    @keydown.escape.window="close()"
>
    <button
        type="button"
        class="tap-action-muted mb-2 min-h-11 w-full px-3 text-sm font-semibold sm:w-auto"
        @click="toggle()"
        :aria-expanded="open"
    >
        {{ __('invoices.pick_catalog_product') }}
    </button>

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
            class="form-field__control w-full"
            x-model="query"
            :placeholder="@js(__('invoices.search_catalog_products'))"
            autocomplete="off"
        />
        <ul class="invoice-catalog-picker__list" role="listbox">
            <template x-for="product in filtered" :key="product.id">
                <li>
                    <button
                        type="button"
                        class="invoice-catalog-picker__option tap-action-muted"
                        @click="pick(product.id)"
                        role="option"
                    >
                        <span class="font-medium" x-text="product.label"></span>
                        <span class="text-xs text-[var(--app-muted)]" x-text="product.meta"></span>
                    </button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-4 text-center text-sm text-[var(--app-muted)]">
                {{ __('invoices.catalog_no_match') }}
            </li>
        </ul>
    </div>
</div>