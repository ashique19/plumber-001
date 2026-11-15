@props(['show' => false, 'standardBuyer' => false])

@php
    $quickCustomerFields = [
        'customerQuickName',
        'customerQuickNameAr',
        'customerQuickPhone',
        'customerQuickEmail',
        'customerQuickVat',
        'customerQuickCr',
        'customerQuickCountry',
        'customerQuickAddrBuilding',
        'customerQuickAddrStreet',
        'customerQuickAddrDistrict',
        'customerQuickAddrCity',
        'customerQuickAddrPostal',
        'customerQuickAddrAdditional',
    ];
@endphp

<x-modal
    :show="$show"
    :title="__('invoices.add_customer')"
    close-wire="$set('showQuickCustomerModal', false)"
    size="lg"
>
    <form class="x-modal-form" wire:submit.prevent="saveQuickCustomer">
        <x-modal.validation-summary :fields="$quickCustomerFields" />

        <div class="x-modal-form__fields x-modal-form__fields--2">
            <x-field.input :label="__('customers.name')" name="customerQuickName" wire:model="customerQuickName" :liveValidate="false" required wrapper-class="md:col-span-2" />
            <x-field.input :label="__('invoices.customer_name_ar')" name="customerQuickNameAr" wire:model="customerQuickNameAr" :liveValidate="false" dir="rtl" wrapper-class="md:col-span-2" />
            <x-field.input :label="__('customers.phone')" name="customerQuickPhone" wire:model="customerQuickPhone" :liveValidate="false" required inputmode="tel" icon="phone" />
            <x-field.input type="email" :label="__('customers.email_optional')" name="customerQuickEmail" wire:model="customerQuickEmail" :liveValidate="false" icon="mail" />
            <x-field.input :label="__('business.fields.vat_number')" name="customerQuickVat" wire:model="customerQuickVat" :liveValidate="false" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.cr_number')" name="customerQuickCr" wire:model="customerQuickCr" :liveValidate="false" :required="$standardBuyer" />
            @if ($standardBuyer)
                <p class="text-xs text-[var(--app-muted)] md:col-span-2">{{ __('invoices.standard_customer_modal_hint') }}</p>
            @endif
            <x-field.input :label="__('invoices.buyer_country')" name="customerQuickCountry" wire:model="customerQuickCountry" :liveValidate="false" maxlength="2" :required="$standardBuyer" wrapper-class="md:col-span-2" />
            <x-field.input :label="__('business.fields.building')" name="customerQuickAddrBuilding" wire:model="customerQuickAddrBuilding" :liveValidate="false" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.street')" name="customerQuickAddrStreet" wire:model="customerQuickAddrStreet" :liveValidate="false" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.district')" name="customerQuickAddrDistrict" wire:model="customerQuickAddrDistrict" :liveValidate="false" />
            <x-field.input :label="__('business.fields.city')" name="customerQuickAddrCity" wire:model="customerQuickAddrCity" :liveValidate="false" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.postal')" name="customerQuickAddrPostal" wire:model="customerQuickAddrPostal" :liveValidate="false" :required="$standardBuyer" />
            <x-field.input :label="__('business.fields.additional')" name="customerQuickAddrAdditional" wire:model="customerQuickAddrAdditional" :liveValidate="false" wrapper-class="md:col-span-2" />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickCustomerModal', false)"
                submit-wire="saveQuickCustomer"
                :submit-label="__('catalog.save_and_select')"
            />
        </div>
    </form>
</x-modal>