@props([
    'categories' => collect(),
    'show' => false,
    'imagePreviewUrl' => null,
    'formTab' => 'basic',
    'tabErrorCounts' => ['basic' => 0, 'details' => 0],
    'priceMode' => 'ex',
    'productStockReady' => false,
    'catalogI18nReady' => false,
    'catalogUnitsSizesReady' => false,
])

@php
    $quickProductFields = [
        'quickProductNameEn',
        'quickProductNameAr',
        'quickProductNameBn',
        'quickProductPrice',
        'quickProductVat',
        'quickProductItemType',
        'quickProductCategoryId',
        'quickProductActive',
        'quickProductTrackStock',
        'quickProductImageUpload',
        'quickProductCost',
        'quickProductSku',
        'quickProductDescriptionEn',
        'quickProductDescriptionAr',
        'quickProductDescriptionBn',
        'quickProductOpeningStock',
        'quickProductLowStockThreshold',
    ];
@endphp

<x-modal
    :show="$show"
    :title="__('invoices.add_product')"
    close-wire="$set('showQuickProductModal', false)"
    size="xl"
    data-testid="quick-product-modal"
>
    <form id="quick-product-form" class="x-modal-form catalog-product-form" wire:submit.prevent="saveQuickProduct">
        <x-modal.validation-summary :fields="$quickProductFields" loading-target="saveQuickProduct,goToQuickProductDetailsTab" />

        <div class="catalog-product-form__body min-h-0 flex flex-1 flex-col overflow-hidden">
            @include('components.catalog.partials.product-modal-body', [
                'variant' => 'quick',
                'formTab' => $formTab,
                'tabErrorCounts' => $tabErrorCounts,
                'setTabMethod' => 'setQuickProductFormTab',
                'liveValidate' => false,
                'nameEn' => 'quickProductNameEn',
                'nameAr' => 'quickProductNameAr',
                'nameBn' => 'quickProductNameBn',
                'priceModel' => 'quickProductPrice',
                'vatModel' => 'quickProductVat',
                'categoryModel' => 'quickProductCategoryId',
                'itemTypeModel' => 'quickProductItemType',
                'activeModel' => 'quickProductActive',
                'costModel' => 'quickProductCost',
                'trackStockModel' => 'quickProductTrackStock',
                'skuModel' => 'quickProductSku',
                'descriptionEnModel' => 'quickProductDescriptionEn',
                'descriptionArModel' => 'quickProductDescriptionAr',
                'descriptionBnModel' => 'quickProductDescriptionBn',
                'openingStockModel' => 'quickProductOpeningStock',
                'lowStockModel' => 'quickProductLowStockThreshold',
                'imageUploadModel' => 'quickProductImageUpload',
                'imageRemoveAction' => 'removeQuickProductImage',
                'imagePreviewUrl' => $imagePreviewUrl,
                'priceMode' => $priceMode,
                'categories' => $categories,
                'catalogI18nReady' => $catalogI18nReady,
                'catalogUnitsSizesReady' => $catalogUnitsSizesReady,
                'productStockReady' => $productStockReady,
            ])
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <div class="x-modal-footer-actions" data-testid="quick-product-form-footer">
                <button
                    type="button"
                    class="tap-action-muted min-h-12 px-5 text-sm font-semibold"
                    wire:click='$set("showQuickProductModal", false)'
                    wire:loading.attr="disabled"
                    wire:target="saveQuickProduct,goToQuickProductDetailsTab"
                >
                    {{ __('catalog.cancel') }}
                </button>
                @if ($formTab === 'basic')
                    <x-ui.livewire-action
                        type="button"
                        wire-click="goToQuickProductDetailsTab"
                        target="goToQuickProductDetailsTab"
                        loading="saving"
                        class="tap-action-muted min-h-12 min-w-[9.5rem] px-5 text-sm font-semibold"
                        variant="muted"
                        data-testid="quick-product-form-more-details"
                    >
                        {{ __('catalog.product_more_details') }}
                    </x-ui.livewire-action>
                @endif
                <x-ui.livewire-action
                    type="submit"
                    target="saveQuickProduct"
                    loading="saving"
                    class="tap-action-primary min-h-12 min-w-[11.5rem] px-5 text-sm font-semibold"
                    variant="primary"
                    data-testid="quick-product-form-primary"
                >
                    {{ __('catalog.save_and_select') }}
                </x-ui.livewire-action>
            </div>
        </div>
    </form>
</x-modal>