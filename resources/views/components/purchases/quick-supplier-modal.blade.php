@props(['show' => false])

@if ($show)
    <div class="app-modal-backdrop" wire:click.self="$set('showQuickSupplierModal', false)">
        <div class="app-modal" role="dialog" aria-modal="true" @click.stop>
            <h2 class="text-lg font-semibold">{{ __('suppliers.quick_add_title') }}</h2>
            <form class="admin-form-grid mt-3 gap-3" wire:submit="saveQuickSupplier">
                <x-field.input :label="__('suppliers.name')" name="quickSupplierName" wire:model.blur="quickSupplierName" required />
                <x-field.input :label="__('suppliers.name_ar')" name="quickSupplierNameAr" wire:model.blur="quickSupplierNameAr" dir="rtl" />
                <div class="admin-form-grid admin-form-grid--2 gap-3">
                    <x-field.input :label="__('suppliers.phone')" name="quickSupplierPhone" wire:model.blur="quickSupplierPhone" inputmode="tel" icon="phone" />
                    <x-field.input :label="__('suppliers.vat')" name="quickSupplierVat" wire:model.blur="quickSupplierVat" inputmode="numeric" />
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <x-ui.livewire-action type="submit" target="saveQuickSupplier" loading="saveQuickSupplier" class="min-h-11 flex-1">
                        {{ __('suppliers.save_and_select') }}
                    </x-ui.livewire-action>
                    <button type="button" class="tap-action-muted min-h-11 flex-1" wire:click="$set('showQuickSupplierModal', false)">
                        {{ __('catalog.cancel') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif