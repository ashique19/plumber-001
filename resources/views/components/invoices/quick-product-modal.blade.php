@props(['categories' => collect(), 'show' => false])

@php
    $quickProductFields = [
        'quickProductNameEn',
        'quickProductNameAr',
        'quickProductPrice',
        'quickProductVat',
        'quickProductItemType',
        'quickProductCategoryId',
        'quickProductTrackStock',
    ];
@endphp

<x-modal
    :show="$show"
    :title="__('invoices.add_product')"
    close-wire="$set('showQuickProductModal', false)"
    size="xl"
    data-testid="quick-product-modal"
>
    <form class="x-modal-form" wire:submit.prevent="saveQuickProduct">
        <x-modal.validation-summary :fields="$quickProductFields" />

        <div class="x-modal-form__fields x-modal-form__fields--2">
            <x-catalog.quick-product-fields :categories="$categories" />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickProductModal', false)"
                submit-wire="saveQuickProduct"
                :submit-label="__('catalog.save_and_select')"
            />
        </div>
    </form>
</x-modal>