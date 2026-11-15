@props(['show' => false])

<x-modal
    :show="$show"
    :title="__('suppliers.quick_add_title')"
    close-wire="$set('showQuickSupplierModal', false)"
    size="md"
>
    <form class="x-modal-form" wire:submit="saveQuickSupplier">
        <div class="x-modal-form__fields x-modal-form__fields--2">
            <x-field.input :label="__('suppliers.name')" name="quickSupplierName" wire:model.blur="quickSupplierName" required wrapper-class="md:col-span-2" />
            <x-field.input :label="__('suppliers.name_ar')" name="quickSupplierNameAr" wire:model.blur="quickSupplierNameAr" dir="rtl" wrapper-class="md:col-span-2" />
            <x-field.input :label="__('suppliers.phone')" name="quickSupplierPhone" wire:model.blur="quickSupplierPhone" inputmode="tel" icon="phone" />
            <x-field.input :label="__('suppliers.vat')" name="quickSupplierVat" wire:model.blur="quickSupplierVat" inputmode="numeric" />
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