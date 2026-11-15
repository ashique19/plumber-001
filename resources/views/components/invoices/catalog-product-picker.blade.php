@props([
    'lineIndex',
    'products' => [],
    'compact' => false,
    'canCreateProduct' => false,
    'search' => '',
])

<div class="invoice-catalog-picker invoice-catalog-picker--inline mb-3">
    <label class="form-field">
        <span class="form-field__label">{{ __('invoices.search_catalog_products') }}</span>
        <div class="flex gap-2">
            <input
                type="search"
                class="form-field__control min-h-11 flex-1"
                wire:model.live.debounce.350ms="catalogLineSearch.{{ $lineIndex }}"
                placeholder="{{ __('invoices.add_line_product_placeholder') }}"
                autocomplete="off"
                inputmode="search"
            />
            @if ($canCreateProduct)
                <button
                    type="button"
                    class="invoice-picker-add tap-action-muted flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold"
                    wire:click="openQuickProductModal({{ $lineIndex }})"
                    aria-label="{{ __('invoices.add_product') }}"
                    title="{{ __('invoices.add_product') }}"
                >
                    +
                </button>
            @endif
        </div>
    </label>

    @if (trim($search) !== '')
        <ul class="invoice-customer-picker__results invoice-catalog-picker__results mt-1" role="listbox">
            @forelse ($products as $product)
                <li wire:key="catalog-line-{{ $lineIndex }}-{{ $product['id'] }}">
                    <button
                        type="button"
                        class="invoice-customer-picker__option tap-action-muted w-full text-start"
                        wire:click="applyCatalogProduct({{ $lineIndex }}, {{ $product['id'] }})"
                        role="option"
                    >
                        <span class="font-medium">{{ $product['label'] }}</span>
                        <span class="block text-xs text-[var(--app-muted)]">{{ $product['meta'] }}</span>
                    </button>
                </li>
            @empty
                <li class="px-3 py-3 text-sm text-[var(--app-muted)]">{{ __('invoices.catalog_no_match') }}</li>
            @endforelse
        </ul>
    @endif
</div>