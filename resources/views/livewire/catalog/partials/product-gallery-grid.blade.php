<div class="catalog-product-gallery mt-4" data-testid="catalog-product-gallery">
    @if ($products->total() === 0)
        <div class="app-empty">
            <p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_products') }}</p>
        </div>
    @else
        <ul class="catalog-product-gallery__grid">
            @foreach ($products as $product)
                <li wire:key="product-tile-{{ $product->id }}">
                    <x-catalog.product-gallery-tile
                        :product="$product"
                        :price-label="number_format(
                            \App\Support\VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate),
                            2,
                        ).' '.__('dashboard.sar')"
                        :initials="\App\Support\Catalog\ProductGallery::initials($product)"
                        :show-stock="$productStockReady ?? false"
                        :can-open="true"
                    />
                </li>
            @endforeach
        </ul>

        <div class="mt-4">
            {{ $products->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>