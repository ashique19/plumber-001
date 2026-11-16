<div class="catalog-product-list mt-4" data-testid="catalog-product-list">
    <ul class="grid grid-cols-1 gap-3 lg:grid-cols-2">
        @forelse($products as $product)
            <li class="app-card overflow-hidden p-0" wire:key="product-row-{{ $product->id }}">
                <div class="flex gap-3 p-3">
                    @if($product->image_url)
                        <img src="{{ asset($product->image_url) }}" alt="" class="h-16 w-16 rounded-lg object-cover" loading="lazy">
                    @else
                        <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-[var(--plumber-sky)] text-xs font-semibold text-[var(--app-muted)]">
                            {{ \App\Support\Catalog\ProductGallery::initials($product) }}
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $product->displayName() }}</p>
                        <p class="text-xs text-[var(--app-muted)]">{{ $product->category?->displayName() }}</p>
                        <p class="mt-1 text-sm font-medium tabular-nums">
                            {{ number_format(\App\Support\VatPricing::incFromEx((float) $product->price_ex_vat, (float) $product->vat_rate), 2) }} {{ __('dashboard.sar') }}
                        </p>
                        @if(($productStockReady ?? false) && $product->track_stock)
                            <p class="mt-1 text-xs tabular-nums text-[var(--app-muted)]">
                                {{ __('catalog.stock_qty', ['qty' => number_format((float) $product->stock_quantity, 0)]) }}
                            </p>
                        @endif
                    </div>
                </div>
                @if($canWrite)
                    <div class="flex flex-wrap items-center gap-2 border-t px-3 py-2 text-xs" style="border-color: var(--app-border)">
                        <button type="button" class="tap-action-muted min-h-9 px-2" wire:click="openProductActions({{ $product->id }})">
                            {{ __('catalog.view_actions') }}
                        </button>
                        <button type="button" class="tap-action-muted ms-auto" wire:click="startProduct({{ $product->id }})">{{ __('catalog.edit') }}</button>
                        <x-ui.livewire-action wire:click="deleteProduct({{ $product->id }})" :target="'deleteProduct('.$product->id.')'" loading="saving" variant="muted" class="tap-action-muted text-rose-600">{{ __('catalog.delete') }}</x-ui.livewire-action>
                    </div>
                @endif
            </li>
        @empty
            <li class="app-empty lg:col-span-2"><p class="text-sm text-[var(--app-muted)]">{{ __('catalog.empty_products') }}</p></li>
        @endforelse
    </ul>

    @if ($products->hasPages())
        <div class="mt-4">
            {{ $products->links(data: ['scrollTo' => false]) }}
        </div>
    @endif
</div>