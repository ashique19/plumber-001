@props([
    'show' => false,
    'missingKeys' => [],
    'customerName' => '',
])

@php
    $showIdentity = in_array('buyer_identity', $missingKeys, true);
@endphp

<x-modal
    :show="$show"
    :title="__('invoices.standard_buyer_completion_title')"
    close-wire="cancelStandardBuyerCompletion"
    size="identity"
    class="x-modal--identity"
    data-testid="standard-buyer-completion-modal"
>
    <form class="x-modal-form" wire:submit.prevent="confirmStandardBuyerCompletion">
        <p class="text-sm text-[var(--app-muted)] px-6 pt-4">
            {{ __('invoices.standard_buyer_completion_intro', ['name' => $customerName]) }}
        </p>

        <div class="x-modal-form__fields !pt-0">
            @if ($showIdentity)
                <x-customers.identity-fields
                    :fields="\App\Support\Customers\CustomerIdentityRules::buyerFields()"
                    more-address-prop="standardBuyerShowMoreAddress"
                    :sa-standard-buyer="true"
                />
                <p class="text-xs text-[var(--app-muted)] admin-modal-span-full">{{ __('invoices.buyer_id_hint') }}</p>
            @else
                @if (in_array('addrBuilding', $missingKeys, true))
                    <x-field.input :label="__('business.fields.building')" name="addrBuilding" wire:model="addrBuilding" :liveValidate="false" required />
                @endif
                @if (in_array('addrStreet', $missingKeys, true))
                    <x-field.input :label="__('business.fields.street')" name="addrStreet" wire:model="addrStreet" :liveValidate="false" required wrapper-class="admin-modal-span-2" />
                @endif
                @if (in_array('addrDistrict', $missingKeys, true))
                    <x-field.input :label="__('business.fields.district')" name="addrDistrict" wire:model="addrDistrict" :liveValidate="false" required />
                @endif
                @if (in_array('addrCity', $missingKeys, true))
                    <x-field.input :label="__('business.fields.city')" name="addrCity" wire:model="addrCity" :liveValidate="false" required />
                @endif
                @if (in_array('addrPostal', $missingKeys, true))
                    <x-field.input :label="__('business.fields.postal')" name="addrPostal" wire:model="addrPostal" :liveValidate="false" required />
                @endif
            @endif
        </div>

        <label class="mx-6 mb-2 flex min-h-12 shrink-0 cursor-pointer items-start gap-3 rounded-lg border border-[var(--app-border)] px-3 py-3 text-sm">
            <input
                type="checkbox"
                class="mt-0.5 h-5 w-5 shrink-0 rounded border-[var(--app-border)]"
                wire:model="standardBuyerPersistToCustomer"
            />
            <span>{{ __('invoices.save_buyer_to_customer') }}</span>
        </label>

        <div class="x-modal__footer x-modal__footer--in-form">
            <x-modal.footer
                cancel-wire="cancelStandardBuyerCompletion"
                submit-wire="confirmStandardBuyerCompletion"
                submit-target="confirmStandardBuyerCompletion"
                :submit-label="__('invoices.standard_buyer_completion_confirm')"
            />
        </div>
    </form>
</x-modal>