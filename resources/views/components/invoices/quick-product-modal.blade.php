@props(['categories' => collect(), 'show' => false])

<x-modal
    :show="$show"
    :title="__('invoices.add_product')"
    close-wire="$set('showQuickProductModal', false)"
    size="md"
>
    <form class="x-modal-form" wire:submit="saveQuickProduct">
        <div class="x-modal-form__fields x-modal-form__fields--2">
            <x-field.input :label="__('catalog.name_en')" name="quickProductNameEn" wire:model.blur="quickProductNameEn" required wrapper-class="md:col-span-2" />
            <x-field.input :label="__('catalog.name_ar')" name="quickProductNameAr" wire:model.blur="quickProductNameAr" required dir="rtl" wrapper-class="md:col-span-2" />
            <x-field.input type="number" step="0.01" min="0" :label="__('catalog.price_ex_vat')" name="quickProductPrice" wire:model.blur="quickProductPrice" required />
            <x-field.input type="number" step="0.01" min="0" max="100" :label="__('catalog.vat_rate')" name="quickProductVat" wire:model.blur="quickProductVat" required />
            <x-field.select :label="__('catalog.item_type')" name="quickProductItemType" wire:model.live="quickProductItemType" wrapper-class="md:col-span-2">
                @foreach (\App\Enums\ProductItemType::cases() as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-field.select>
            <x-field.select :label="__('catalog.category')" name="quickProductCategoryId" wire:model.live="quickProductCategoryId" wrapper-class="md:col-span-2">
                <option value="">—</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name_en }}</option>
                @endforeach
            </x-field.select>
            @if (\App\Support\Inventory\ProductStockSchema::ready())
                <label class="flex min-h-12 items-center gap-2 text-sm md:col-span-2">
                    <input type="checkbox" wire:model="quickProductTrackStock" class="rounded border-[var(--app-border)]" />
                    {{ __('stock.track_stock') }}
                </label>
            @endif
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickProductModal', false)"
                submit-target="saveQuickProduct"
                :submit-label="__('catalog.save_and_select')"
            />
        </div>
    </form>
</x-modal>