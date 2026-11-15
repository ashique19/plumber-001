@props([
    'results',
    'canCreateProduct' => false,
    'search' => '',
])

<div class="invoice-add-line-search mb-3">
    <div class="flex gap-2">
        <div class="min-w-0 flex-1">
            <x-field.search
                :label="__('invoices.search_catalog_products')"
                name="addLineProductSearch"
                wire:model.live.debounce.350ms="addLineProductSearch"
                :placeholder="__('invoices.add_line_product_placeholder')"
                :clearable="trim($search) !== ''"
                clear-action="$set('addLineProductSearch', '')"
            />
        </div>
        @if ($canCreateProduct)
            <button
                type="button"
                class="invoice-picker-add tap-action-muted mt-7 flex min-h-12 min-w-12 shrink-0 items-center justify-center rounded-xl text-lg font-bold"
                wire:click="openQuickProductModal"
                aria-label="{{ __('invoices.add_product') }}"
            >
                +
            </button>
        @endif
    </div>
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
</div>