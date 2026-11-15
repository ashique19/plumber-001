@props(['show' => false, 'standardBuyer' => false])

@if ($show)
    <div class="app-modal-backdrop" wire:click.self="$set('showQuickCustomerModal', false)">
        <div class="app-modal invoice-quick-customer-modal" role="dialog" aria-modal="true" @click.stop>
            <h2 class="text-lg font-semibold">{{ __('invoices.add_customer') }}</h2>
            <form class="mt-3 grid gap-3" wire:submit="saveQuickCustomer">
                <x-field.input :label="__('customers.name')" name="customerQuickName" wire:model.blur="customerQuickName" required />
                <x-field.input :label="__('invoices.customer_name_ar')" name="customerQuickNameAr" wire:model.blur="customerQuickNameAr" dir="rtl" />
                <x-field.input :label="__('customers.phone')" name="customerQuickPhone" wire:model.blur="customerQuickPhone" required inputmode="tel" />
                <x-field.input type="email" :label="__('customers.email_optional')" name="customerQuickEmail" wire:model.blur="customerQuickEmail" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field.input :label="__('business.fields.vat_number')" name="customerQuickVat" wire:model.blur="customerQuickVat" :required="$standardBuyer" />
                    <x-field.input :label="__('business.fields.cr_number')" name="customerQuickCr" wire:model.blur="customerQuickCr" :required="$standardBuyer" />
                </div>
                @if ($standardBuyer)
                    <p class="text-xs text-[var(--app-muted)]">{{ __('invoices.standard_customer_modal_hint') }}</p>
                @endif
                <x-field.input :label="__('invoices.buyer_country')" name="customerQuickCountry" wire:model.blur="customerQuickCountry" maxlength="2" :required="$standardBuyer" />
                <x-field.input :label="__('business.fields.building')" name="customerQuickAddrBuilding" wire:model.blur="customerQuickAddrBuilding" :required="$standardBuyer" />
                <x-field.input :label="__('business.fields.street')" name="customerQuickAddrStreet" wire:model.blur="customerQuickAddrStreet" :required="$standardBuyer" />
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field.input :label="__('business.fields.district')" name="customerQuickAddrDistrict" wire:model.blur="customerQuickAddrDistrict" />
                    <x-field.input :label="__('business.fields.city')" name="customerQuickAddrCity" wire:model.blur="customerQuickAddrCity" :required="$standardBuyer" />
                </div>
                <x-field.input :label="__('business.fields.postal')" name="customerQuickAddrPostal" wire:model.blur="customerQuickAddrPostal" :required="$standardBuyer" />
                <x-field.input :label="__('business.fields.additional')" name="customerQuickAddrAdditional" wire:model.blur="customerQuickAddrAdditional" />
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-ui.livewire-action type="submit" target="saveQuickCustomer" loading="saveQuickCustomer" class="min-h-11 flex-1">
                        {{ __('customers.save') }}
                    </x-ui.livewire-action>
                    <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="$set('showQuickCustomerModal', false)">
                        {{ __('catalog.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif