@props(['show' => false, 'standardBuyer' => false])

<x-modal
    :show="$show"
    :title="__('invoices.add_customer')"
    close-wire="$set('showQuickCustomerModal', false)"
    size="lg"
>
    <form class="x-modal-form" wire:submit="saveQuickCustomer">
        <div class="x-modal-form__fields x-modal-form__fields--2">
            <x-field.input :label="__('customers.name')" name="customerQuickName" wire:model.blur="customerQuickName" required wrapper-class="md:col-span-2" />
            <x-field.input :label="__('invoices.customer_name_ar')" name="customerQuickNameAr" wire:model.blur="customerQuickNameAr" dir="rtl" wrapper-class="md:col-span-2" />
            <x-field.input :label="__('customers.phone')" name="customerQuickPhone" wire:model.blur="customerQuickPhone" required inputmode="tel" icon="phone" />
            <x-field.input type="email" :label="__('customers.email_optional')" name="customerQuickEmail" wire:model.blur="customerQuickEmail" icon="mail" />
            <x-field.input :label="__('business.fields.vat_number')" name="customerQuickVat" wire:model.blur="customerQuickVat" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.cr_number')" name="customerQuickCr" wire:model.blur="customerQuickCr" :required="$standardBuyer" />
            @if ($standardBuyer)
                <p class="text-xs text-[var(--app-muted)] md:col-span-2">{{ __('invoices.standard_customer_modal_hint') }}</p>
            @endif
            <x-field.input :label="__('invoices.buyer_country')" name="customerQuickCountry" wire:model.blur="customerQuickCountry" maxlength="2" :required="$standardBuyer" wrapper-class="md:col-span-2" />
            <x-field.input :label="__('business.fields.building')" name="customerQuickAddrBuilding" wire:model.blur="customerQuickAddrBuilding" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.street')" name="customerQuickAddrStreet" wire:model.blur="customerQuickAddrStreet" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.district')" name="customerQuickAddrDistrict" wire:model.blur="customerQuickAddrDistrict" />
            <x-field.input :label="__('business.fields.city')" name="customerQuickAddrCity" wire:model.blur="customerQuickAddrCity" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.postal')" name="customerQuickAddrPostal" wire:model.blur="customerQuickAddrPostal" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.additional')" name="customerQuickAddrAdditional" wire:model.blur="customerQuickAddrAdditional" wrapper-class="md:col-span-2" />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickCustomerModal', false)"
                submit-target="saveQuickCustomer"
                :submit-label="__('catalog.save_and_select')"
            />
        </div>
    </form>
</x-modal>