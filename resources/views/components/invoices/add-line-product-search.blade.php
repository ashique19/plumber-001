@props([
    'results',
    'canCreateProduct' => false,
    'search' => '',
])

<div class="invoice-add-line-search mb-3">
    <label class="form-field">
        <span class="form-field__label">{{ __('invoices.add_line_product_search') }}</span>
        <div class="flex gap-2">
            <input
                type="search"
                class="form-field__control min-h-11 flex-1"
                wire:model.live.debounce.350ms="addLineProductSearch"
                placeholder="{{ __('invoices.add_line_product_placeholder') }}"
                autocomplete="off"
            />
            @if ($canCreateProduct)
                <button
                    type="button"
                    class="invoice-picker-add tap-action-muted flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-xl text-lg font-bold"
                    wire:click="openQuickProductModal"
                    aria-label="{{ __('invoices.add_product') }}"
                >
                    +
                </button>
            @endif
        </div>
    </label>
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