<div class="pb-24 lg:pb-8">
    <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="app-page-title">{{ __('stock.title') }}</h1>
            <p class="app-page-lead">{{ __('stock.subtitle') }}</p>
        </div>
        @can('inventory.manage')
            <a href="{{ route('admin.purchases.create') }}" class="tap-action-primary min-h-11 items-center gap-2 px-4 text-sm">
                <x-app.icon name="plus" class="h-4 w-4" />
                {{ __('purchases.create_title') }}
            </a>
        @endcan
    </header>

    @unless($schemaReady)
        <p class="app-card p-4 text-sm text-[var(--app-muted)]">{{ __('stock.schema_missing') }}</p>
    @else
        <div class="app-admin-filter mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <x-field.input
                type="search"
                :label="__('stock.search')"
                name="search"
                wire:model.live.debounce.300ms="search"
                :placeholder="__('stock.search_placeholder')"
            />
            <label class="flex min-h-11 cursor-pointer items-center gap-2 text-sm">
                <input type="checkbox" class="h-4 w-4" wire:model.live="lowStockOnly">
                {{ __('stock.low_stock_only') }}
            </label>
        </div>

        <section class="app-card mb-4 overflow-hidden p-0">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)]/60 text-xs text-[var(--app-muted)]">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('stock.product') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('stock.on_hand') }}</th>
                        <th class="px-4 py-3 text-end font-medium">{{ __('stock.cost') }}</th>
                        <th class="px-4 py-3 text-end font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr class="border-b border-[var(--app-border)] last:border-0" wire:key="stock-product-{{ $product->id }}">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $product->displayName() }}</p>
                                @if(filled($product->sku))
                                    <p class="text-xs text-[var(--app-muted)]">{{ $product->sku }}</p>
                                @endif
                                @if((float) $product->stock_quantity <= (float) $product->low_stock_threshold)
                                    <span class="pos-status-pill pos-status-pill--cancelled mt-1">{{ __('stock.low_stock_badge') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ number_format((float) $product->stock_quantity, 2) }}</td>
                            <td class="px-4 py-3 text-end tabular-nums">{{ number_format((float) $product->cost_ex_vat, 2) }}</td>
                            <td class="px-4 py-3 text-end">
                                <button type="button" class="tap-action-muted text-xs" wire:click="selectProduct({{ $product->id }})">
                                    {{ __('stock.view_movements') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-[var(--app-muted)]">{{ __('stock.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            @if($products instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
                <x-admin.hub-pager class="border-t border-[var(--app-border)] px-4 py-2" :paginator="$products" />
            @endif
        </section>

        <section class="app-card p-4">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">{{ __('stock.movements_heading') }}</h2>
                @if($selected)
                    <button type="button" class="tap-action-muted text-xs" wire:click="clearProduct">{{ __('stock.clear_filter') }}</button>
                @endif
            </div>
            @if($selected)
                <p class="mt-1 text-xs text-[var(--app-muted)]">{{ $selected->displayName() }}</p>
            @endif
            <div class="-mx-4 mt-3 overflow-x-auto px-4">
                <table class="w-full min-w-[28rem] text-left text-xs sm:text-sm">
                    <thead class="text-[var(--app-muted)]">
                        <tr class="border-b border-[var(--app-border)]">
                            <th class="pb-2 pe-2 font-medium">{{ __('stock.movement_when') }}</th>
                            <th class="pb-2 px-2 font-medium">{{ __('stock.movement_type') }}</th>
                            <th class="pb-2 px-2 font-medium">{{ __('stock.product') }}</th>
                            <th class="pb-2 ps-2 text-end font-medium">{{ __('stock.movement_delta') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $movement)
                            <tr class="border-b border-[var(--app-border)] last:border-0" wire:key="movement-{{ $movement->id }}">
                                <td class="py-2 pe-2 tabular-nums">{{ $movement->created_at?->timezone('Asia/Riyadh')->format('Y-m-d H:i') }}</td>
                                <td class="py-2 px-2">{{ __('stock.movement_types.'.$movement->type) }}</td>
                                <td class="py-2 px-2">{{ $movement->product?->displayName() }}</td>
                                <td class="py-2 ps-2 text-end tabular-nums font-medium @if((float) $movement->quantity_delta < 0) text-red-700 @elseif((float) $movement->quantity_delta > 0) text-emerald-800 @endif">
                                    {{ number_format((float) $movement->quantity_delta, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-[var(--app-muted)]">{{ __('stock.no_movements') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endunless
</div>