@props(['categories' => collect(), 'show' => false])

@if ($show)
    <div class="app-modal-backdrop" wire:click.self="$set('showQuickProductModal', false)">
        <div class="app-modal" role="dialog" aria-modal="true" @click.stop>
            <h2 class="text-lg font-semibold">{{ __('invoices.add_product') }}</h2>
            <form class="mt-3 grid gap-3" wire:submit="saveQuickProduct">
                <x-field.input :label="__('catalog.name_en')" name="quickProductNameEn" wire:model.blur="quickProductNameEn" required />
                <x-field.input :label="__('catalog.name_ar')" name="quickProductNameAr" wire:model.blur="quickProductNameAr" required dir="rtl" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field.input type="number" step="0.01" min="0" :label="__('catalog.price_ex_vat')" name="quickProductPrice" wire:model.blur="quickProductPrice" required />
                    <x-field.input type="number" step="0.01" min="0" max="100" :label="__('catalog.vat_rate')" name="quickProductVat" wire:model.blur="quickProductVat" required />
                </div>
                <x-field.select :label="__('catalog.item_type')" name="quickProductItemType" wire:model.live="quickProductItemType">
                    @foreach (\App\Enums\ProductItemType::cases() as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-field.select>
                <x-field.select :label="__('catalog.category')" name="quickProductCategoryId" wire:model.live="quickProductCategoryId">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name_en }}</option>
                    @endforeach
                </x-field.select>
                @if (\App\Support\Inventory\ProductStockSchema::ready())
                    <label class="flex min-h-11 items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="quickProductTrackStock" class="rounded border-[var(--app-border)]" />
                        {{ __('stock.track_stock') }}
                    </label>
                @endif
                <div class="flex flex-wrap gap-2">
                    <x-ui.livewire-action type="submit" target="saveQuickProduct" loading="saveQuickProduct" class="min-h-11 flex-1">
                        {{ __('catalog.save') }}
                    </x-ui.livewire-action>
                    <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="$set('showQuickProductModal', false)">
                        {{ __('catalog.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif