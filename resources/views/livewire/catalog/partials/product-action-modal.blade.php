@if ($showProductActionModal && $productAction)
    @php
        $priceInc = number_format(
            \App\Support\VatPricing::incFromEx((float) $productAction->price_ex_vat, (float) $productAction->vat_rate),
            2,
        );
        $canInvoice = auth('staff')->user()?->can('invoices.issue') ?? false;
    @endphp
    <x-modal
        :show="true"
        :title="$productAction->displayName()"
        close-wire="closeProductActions"
        size="md"
        data-testid="catalog-product-action-modal"
    >
        <div class="space-y-4">
            <div class="flex gap-3">
                @if ($productAction->image_url)
                    <img src="{{ asset($productAction->image_url) }}" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover" loading="lazy">
                @else
                    <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-xl bg-[var(--plumber-sky)] text-lg font-semibold text-[var(--app-muted)]">
                        {{ \App\Support\Catalog\ProductGallery::initials($productAction) }}
                    </div>
                @endif
                <dl class="min-w-0 flex-1 space-y-1 text-sm">
                    <div>
                        <dt class="text-xs text-[var(--app-muted)]">{{ __('catalog.item_type') }}</dt>
                        <dd class="font-medium">{{ $productAction->itemType()->label() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-[var(--app-muted)]">{{ __('catalog.price') }}</dt>
                        <dd class="font-semibold tabular-nums">{{ $priceInc }} {{ __('dashboard.sar') }}</dd>
                    </div>
                    @if (($productStockReady ?? false) && $productAction->track_stock)
                        <div>
                            <dt class="text-xs text-[var(--app-muted)]">{{ __('catalog.stock_on_hand') }}</dt>
                            <dd class="font-medium tabular-nums">
                                {{ __('catalog.stock_qty', ['qty' => number_format((float) $productAction->stock_quantity, 0)]) }}
                            </dd>
                        </div>
                    @endif
                    @if (filled($productAction->displayDescription()))
                        <p class="text-xs leading-relaxed text-[var(--app-muted)]" dir="auto">{{ \Illuminate\Support\Str::limit($productAction->displayDescription(), 160) }}</p>
                    @endif
                </dl>
            </div>

            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @if ($canWrite)
                    <li>
                        <button type="button" class="catalog-product-action-btn tap-action-muted w-full" wire:click="startProduct({{ $productAction->id }})">
                            {{ __('catalog.action_edit') }}
                        </button>
                    </li>
                    <li>
                        <button type="button" class="catalog-product-action-btn tap-action-muted w-full" wire:click="startProduct({{ $productAction->id }})">
                            {{ __('catalog.action_adjust_price') }}
                        </button>
                    </li>
                    @if ($productStockReady ?? false)
                        <li>
                            <button type="button" class="catalog-product-action-btn tap-action-muted w-full" wire:click="startProduct({{ $productAction->id }})">
                                {{ __('catalog.action_adjust_stock') }}
                            </button>
                        </li>
                    @endif
                    <li>
                        <button type="button" class="catalog-product-action-btn tap-action-muted w-full" wire:click="duplicateProduct({{ $productAction->id }})">
                            {{ __('catalog.action_duplicate') }}
                        </button>
                    </li>
                    @if ($productAction->is_active)
                        <li>
                            <button type="button" class="catalog-product-action-btn tap-action-muted w-full" wire:click="archiveProduct({{ $productAction->id }})">
                                {{ __('catalog.action_archive') }}
                            </button>
                        </li>
                    @endif
                    <li class="sm:col-span-2">
                        <x-ui.livewire-action
                            wire:click="deleteProduct({{ $productAction->id }})"
                            :target="'deleteProduct('.$productAction->id.')'"
                            loading="saving"
                            variant="muted"
                            class="catalog-product-action-btn tap-action-muted w-full text-rose-700"
                            wire:confirm="{{ __('catalog.confirm_delete_product') }}"
                        >
                            {{ __('catalog.delete') }}
                        </x-ui.livewire-action>
                    </li>
                @endif
                @if ($canInvoice)
                    <li class="sm:col-span-2">
                        <a
                            class="catalog-product-action-btn tap-action-primary flex w-full items-center justify-center"
                            href="{{ route('admin.invoices.issue', ['mode' => 'simplified', 'add_product' => $productAction->id, 'locale' => app()->getLocale()]) }}"
                        >
                            {{ __('catalog.action_add_to_invoice') }}
                        </a>
                    </li>
                @endif
            </ul>
        </div>
    </x-modal>
@endif