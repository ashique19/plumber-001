@props(['show' => false, 'standardBuyer' => false, 'editMode' => false])

@php
    $quickCustomerFields = array_values(\App\Support\Customers\CustomerIdentityRules::quickFields());
@endphp

<x-modal
    :show="$show"
    :title="$editMode ? __('invoices.edit_customer') : __('invoices.add_customer')"
    close-wire="$set('showQuickCustomerModal', false)"
    size="identity"
    class="x-modal--identity"
    data-testid="quick-customer-modal"
>
    <form class="x-modal-form" wire:submit.prevent="saveQuickCustomer">
        <x-modal.validation-summary :fields="$quickCustomerFields" loading-target="saveQuickCustomer" />

        <div class="x-modal-form__fields">
            <x-customers.identity-fields
                :fields="\App\Support\Customers\CustomerIdentityRules::quickFields()"
                more-address-prop="customerQuickShowMoreAddress"
                :sa-standard-buyer="$standardBuyer"
                :minimal-identity="! $standardBuyer"
            />
        </div>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="$set('showQuickCustomerModal', false)"
                submit-target="saveQuickCustomer"
                :submit-label="$editMode ? __('invoices.save_customer_profile') : __('catalog.save_and_select')"
            />
        </div>
    </form>
</x-modal>