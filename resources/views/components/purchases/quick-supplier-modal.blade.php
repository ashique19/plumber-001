@props(['show' => false])

@php
    $quickSupplierFields = [
        'quickSupplierName',
        'quickSupplierNameAr',
        'quickSupplierPhone',
        'quickSupplierVat',
    ];
@endphp

<x-modal
    :show="$show"
    :title="__('suppliers.quick_add_title')"
    close-wire="$set('showQuickSupplierModal', false)"
    size="identity"
    class="x-modal--identity"
>
    <form class="x-modal-form" wire:submit.prevent="saveQuickSupplier">
        <x-modal.validation-summary :fields="$quickSupplierFields" loading-target="saveQuickSupplier" />

        <div class="x-modal-form__fields x-modal-form__fields--3">
            <x-field.input :label="__('suppliers.name')" name="quickSupplierName" wire:model="quickSupplierName" :liveValidate="false" required />
            <x-field.input :label="__('suppliers.name_ar')" name="quickSupplierNameAr" wire:model="quickSupplierNameAr" :liveValidate="false" dir="rtl" />
            <x-field.input :label="__('suppliers.phone')" name="quickSupplierPhone" wire:model="quickSupplierPhone" :liveValidate="false" inputmode="tel" icon="phone" />
            <x-field.input :label="__('suppliers.vat')" name="quickSupplierVat" wire:model="quickSupplierVat" :liveValidate="false" inputmode="numeric" />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickSupplierModal', false)"
                submit-target="saveQuickSupplier"
                :submit-label="__('suppliers.save_and_select')"
            />
        </div>
    </form>
</x-modal>