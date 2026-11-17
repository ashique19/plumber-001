@props(['categories' => collect()])

<div class="catalog-quick-product-fields admin-modal-field-grid admin-modal-field-grid--2 col-span-full w-full min-w-0" data-testid="quick-product-fields">
    <x-field.input
        :label="__('catalog.name_en')"
        name="quickProductNameEn"
        wire:model="quickProductNameEn"
        :liveValidate="false"
        required
        hint-position="after"
        :hint="' '"
        wrapper-class="quick-product-modal__name-field quick-product-modal__name-field--en"
    />
    <x-field.input
        :label="__('catalog.name_ar')"
        :hint="__('catalog.name_ar_hint')"
        hint-position="after"
        name="quickProductNameAr"
        wire:model="quickProductNameAr"
        :liveValidate="false"
        required
        dir="rtl"
        wrapper-class="quick-product-modal__name-field quick-product-modal__name-field--ar"
    />
    <x-field.input type="number" step="0.01" min="0" :label="__('catalog.price_ex_vat')" name="quickProductPrice" wire:model="quickProductPrice" :liveValidate="false" required />
    <x-field.input type="number" step="0.01" min="0" max="100" :label="__('catalog.vat_rate')" name="quickProductVat" wire:model="quickProductVat" :liveValidate="false" required />
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
        <label class="catalog-quick-product-fields__span-full flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" wire:model="quickProductTrackStock" class="rounded border-[var(--app-border)]" />
            {{ __('stock.track_stock') }}
        </label>
    @endif
</div>