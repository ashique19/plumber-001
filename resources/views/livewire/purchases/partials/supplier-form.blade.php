@props(['purchaseCount' => 0, 'editing' => false])

<form class="admin-form-grid space-y-0" wire:submit="saveSupplier">
    <div class="admin-form-grid admin-form-grid--2 gap-3">
        <x-field.input :label="__('suppliers.name')" name="formName" wire:model.blur="formName" required />
        <x-field.input :label="__('suppliers.name_ar')" name="formNameAr" wire:model.blur="formNameAr" dir="rtl" />
    </div>
    <div class="admin-form-grid admin-form-grid--2 gap-3">
        <x-field.input :label="__('suppliers.contact_person')" name="formContactPerson" wire:model.blur="formContactPerson" />
        <x-field.input :label="__('suppliers.phone')" name="formPhone" wire:model.blur="formPhone" inputmode="tel" icon="phone" />
    </div>
    <div class="admin-form-grid admin-form-grid--2 gap-3">
        <x-field.input type="email" :label="__('suppliers.email')" name="formEmail" wire:model.blur="formEmail" icon="mail" />
        <x-field.input :label="__('suppliers.vat')" name="formVat" wire:model.blur="formVat" inputmode="numeric" />
    </div>
    <div class="admin-form-grid admin-form-grid--2 gap-3">
        <x-field.input :label="__('suppliers.cr_number')" name="formCr" wire:model.blur="formCr" />
        <x-field.input :label="__('suppliers.city')" name="formCity" wire:model.blur="formCity" />
    </div>
    <x-field.textarea :label="__('suppliers.address')" name="formAddress" wire:model.blur="formAddress" rows="2" />
    <x-field.textarea :label="__('suppliers.notes')" name="formNotes" wire:model.blur="formNotes" rows="2" />
    <x-field.toggle :label="__('suppliers.active')" name="formActive" wire:model="formActive" />

    @if ($editing && $purchaseCount > 0)
        <p class="text-xs text-[var(--app-muted)]">{{ __('suppliers.purchase_count_hint', ['count' => $purchaseCount]) }}</p>
    @endif

    <x-field.actions class="mt-2">
        <x-ui.livewire-action type="submit" target="saveSupplier" loading="saveSupplier" class="tap-action-primary min-h-11 flex-1">
            {{ __('suppliers.save') }}
        </x-ui.livewire-action>
        @if ($editing)
            <x-ui.livewire-action type="button" wire:click="deleteSupplier" target="deleteSupplier" loading="deleteSupplier" class="tap-action-muted min-h-11 flex-1">
                {{ __('suppliers.delete') }}
            </x-ui.livewire-action>
        @else
            <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="closeFormModal">{{ __('catalog.cancel') }}</button>
        @endif
    </x-field.actions>
</form>