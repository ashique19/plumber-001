@props([
    'product',
    'priceLabel',
    'initials',
    'showStock' => false,
    'canOpen' => true,
])

@php
    $inactive = ! $product->is_active;
@endphp

<article @class(['catalog-product-tile', 'catalog-product-tile--inactive' => $inactive])>
    <button
        type="button"
        class="catalog-product-tile__media"
        @if ($canOpen)
            wire:click="openProductActions({{ $product->id }})"
            aria-label="{{ __('catalog.open_product_actions', ['name' => $product->displayName()]) }}"
        @endif
    >
        @if ($product->image_url)
            <img
                src="{{ asset($product->image_url) }}"
                alt=""
                class="catalog-product-tile__img"
                loading="lazy"
                decoding="async"
            >
        @else
            <div class="catalog-product-tile__placeholder" aria-hidden="true">
                <x-app.icon name="photo" class="catalog-product-tile__placeholder-icon" />
                <span class="catalog-product-tile__initials">{{ $initials }}</span>
            </div>
        @endif
        <span class="catalog-product-tile__overlay" aria-hidden="true">
            <span class="catalog-product-tile__overlay-label">{{ __('catalog.view_actions') }}</span>
        </span>
    </button>

    <div class="catalog-product-tile__meta">
        <p class="catalog-product-tile__name" dir="auto">{{ $product->displayName() }}</p>
        <p class="catalog-product-tile__price tabular-nums">{{ $priceLabel }}</p>
        @if ($showStock && $product->track_stock)
            <p class="catalog-product-tile__stock tabular-nums">
                {{ __('catalog.stock_qty', ['qty' => number_format((float) $product->stock_quantity, 0)]) }}
            </p>
        @endif
        @if ($inactive)
            <p class="catalog-product-tile__badge">{{ __('catalog.inactive') }}</p>
        @endif
    </div>
</article>