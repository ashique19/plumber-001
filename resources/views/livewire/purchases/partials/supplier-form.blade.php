@props(['purchaseCount' => 0, 'editing' => false])

<form class="x-modal-form" wire:submit="saveSupplier" data-testid="supplier-hub-form">
    <div class="x-modal-form__fields x-modal-form__fields--3" data-testid="supplier-modal-fields">
        <x-field.input :label="__('suppliers.name')" name="formName" wire:model.blur="formName" required />
        <x-field.input :label="__('suppliers.name_ar')" name="formNameAr" wire:model.blur="formNameAr" dir="rtl" />
        <x-field.input :label="__('suppliers.contact_person')" name="formContactPerson" wire:model.blur="formContactPerson" />
        <x-field.input :label="__('suppliers.phone')" name="formPhone" wire:model.blur="formPhone" inputmode="tel" icon="phone" />
        <x-field.input type="email" :label="__('suppliers.email')" name="formEmail" wire:model.blur="formEmail" icon="mail" />
        <x-field.input :label="__('suppliers.vat')" name="formVat" wire:model.blur="formVat" inputmode="numeric" />
        <x-field.input :label="__('suppliers.cr_number')" name="formCr" wire:model.blur="formCr" />
        <x-field.input :label="__('suppliers.city')" name="formCity" wire:model.blur="formCity" />
        <div class="flex min-h-0 items-end pb-1 lg:pb-0">
            <x-field.toggle :label="__('suppliers.active')" name="formActive" wire:model="formActive" class="w-full" />
        </div>
        <x-field.textarea :label="__('suppliers.address')" name="formAddress" wire:model.blur="formAddress" rows="2" wrapper-class="col-span-full" />
        <x-field.textarea :label="__('suppliers.notes')" name="formNotes" wire:model.blur="formNotes" rows="2" wrapper-class="col-span-full" />

        @if ($editing && $purchaseCount > 0)
            <p class="col-span-full text-xs text-[var(--app-muted)]">{{ __('suppliers.purchase_count_hint', ['count' => $purchaseCount]) }}</p>
        @endif
    </div>

    <div class="x-modal__footer x-modal__footer--in-form">
        <div class="x-modal-footer-actions w-full">
            @if ($editing)
                <x-ui.livewire-action type="button" wire:click="deleteSupplier" target="deleteSupplier" loading="deleteSupplier" class="tap-action-muted min-h-12 px-4 text-sm font-semibold me-auto">
                    {{ __('suppliers.delete') }}
                </x-ui.livewire-action>
            @endif
            <button type="button" class="tap-action-muted min-h-12 px-5 text-sm font-semibold" wire:click="closeFormModal">
                {{ __('catalog.cancel') }}
            </button>
            <x-ui.livewire-action type="submit" target="saveSupplier" loading="saveSupplier" class="tap-action-primary min-h-12 px-5 text-sm font-semibold" variant="primary">
                {{ __('suppliers.save') }}
            </x-ui.livewire-action>
        </div>
    </div>
</form>