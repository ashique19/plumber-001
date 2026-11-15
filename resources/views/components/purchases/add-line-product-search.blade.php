@props([
    'results',
    'canCreateProduct' => false,
    'search' => '',
])

<div class="purchase-add-line-search mb-3">
    <x-admin.typeahead-control
        :label="__('invoices.search_catalog_products')"
        name="addLineProductSearch"
        wire:model.live.debounce.350ms="addLineProductSearch"
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
                    <li wire:key="purchase-line-product-{{ $product->id }}">
                        <button
                            type="button"
                            class="invoice-customer-picker__option tap-action-muted w-full text-start"
                            wire:click="addPurchaseLineFromProduct({{ $product->id }})"
                        >
                            <span class="font-medium">{{ $product->displayName() }}</span>
                            <span class="text-xs text-[var(--app-muted)]">{{ $product->sku }} · {{ __('purchases.unit_cost_ex_vat') }} {{ number_format((float) $product->cost_ex_vat, 2) }}</span>
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-2 text-sm text-[var(--app-muted)]">{{ __('invoices.catalog_no_match') }}</li>
                @endforelse
            </ul>
        @endif
    </x-admin.typeahead-control>
</div>