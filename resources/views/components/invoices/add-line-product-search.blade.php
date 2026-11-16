@props([
    'results',
    'canCreateProduct' => false,
    'search' => '',
])

<div class="invoice-add-line-search mb-3" wire:key="invoice-add-line-product-search">
    <x-admin.typeahead-control
        :label="__('invoices.search_catalog_products')"
        name="addLineProductSearch"
        wire:model.live.debounce.200ms="addLineProductSearch"
        :placeholder="__('invoices.add_line_product_placeholder')"
        :clearable="trim($search) !== ''"
        clear-action="$set('addLineProductSearch', '')"
        :show-add="$canCreateProduct"
        add-action="openQuickProductModal"
        :add-label="__('invoices.add_product')"
        :results-open="trim($search) !== ''"
    >
        @if (trim($search) !== '')
            <ul class="invoice-customer-picker__results mt-1">
                @forelse ($results as $product)
                    <li wire:key="add-line-product-{{ $product->id }}">
                        <button
                            type="button"
                            class="invoice-customer-picker__option tap-action-muted w-full text-start"
                            wire:click="addLineFromProduct({{ $product->id }})"
                        >
                            <span class="font-medium">{{ $product->displayName() }}</span>
                            <span class="text-xs text-[var(--app-muted)]">{{ $product->sku }} · {{ number_format((float) $product->price_ex_vat, 2) }} SAR</span>
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-2 text-sm text-[var(--app-muted)]">{{ __('invoices.catalog_no_match') }}</li>
                @endforelse
            </ul>
        @endif
    </x-admin.typeahead-control>
</div>