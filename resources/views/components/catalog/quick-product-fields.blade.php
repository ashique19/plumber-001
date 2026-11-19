@props(['categories' => collect(), 'imagePreviewUrl' => null])

<div class="catalog-quick-product-fields admin-modal-field-grid admin-modal-field-grid--2 admin-modal-field-grid--3 col-span-full w-full min-w-0" data-testid="quick-product-fields">
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
            <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
        @endforeach
    </x-field.select>
    <div class="quick-product-image-field catalog-quick-product-fields__span-full" data-testid="quick-product-image-field">
        <label class="form-field block w-full min-w-0">
            <span class="form-field__label">{{ __('catalog.product_image.label') }}</span>
            <span class="form-field__hint">{{ __('catalog.product_image.optional_hint') }}</span>

            <div class="quick-product-image-field__body mt-2 flex flex-wrap items-start gap-3">
                @if (filled($imagePreviewUrl))
                    <div wire:loading.remove wire:target="quickProductImageUpload" class="quick-product-image-field__preview shrink-0">
                        <img
                            src="{{ $imagePreviewUrl }}"
                            alt=""
                            class="quick-product-image-field__thumb size-20 rounded-lg border border-[var(--app-border)] object-cover"
                            width="80"
                            height="80"
                            loading="lazy"
                            decoding="async"
                        >
                    </div>
                @endif

                <div wire:loading wire:target="quickProductImageUpload" class="text-sm text-[var(--app-muted)]">
                    {{ __('catalog.product_image.uploading') }}…
                </div>

                <div class="quick-product-image-field__actions flex min-w-0 flex-1 flex-col gap-2">
                    <input
                        type="file"
                        wire:model="quickProductImageUpload"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full min-w-0 max-w-full text-sm file:me-2 file:rounded-lg file:border-0 file:bg-[var(--app-surface-alt)] file:px-3 file:py-2 file:text-sm file:font-medium"
                        data-testid="quick-product-image-input"
                    >

                    @if (filled($imagePreviewUrl))
                        <button
                            type="button"
                            class="tap-action-muted self-start text-sm font-medium text-[var(--app-muted)] underline-offset-2 hover:underline"
                            wire:click="removeQuickProductImage"
                            wire:loading.attr="disabled"
                        >
                            {{ __('catalog.product_image.remove') }}
                        </button>
                    @endif
                </div>
            </div>

            @error('quickProductImageUpload')
                <span class="form-field__error mt-1 block" role="alert">{{ $message }}</span>
            @enderror
        </label>
    </div>
    @if (\App\Support\Inventory\ProductStockSchema::ready())
        <label class="catalog-quick-product-fields__span-full flex min-h-11 items-center gap-2 text-sm">
            <input type="checkbox" wire:model="quickProductTrackStock" class="rounded border-[var(--app-border)]" />
            {{ __('stock.track_stock') }}
        </label>
    @endif
</div>