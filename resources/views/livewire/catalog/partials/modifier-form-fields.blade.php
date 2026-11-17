<div class="catalog-entity-form-fields admin-modal-field-grid admin-modal-field-grid--3" data-testid="catalog-modifier-form-fields">
    <x-field.input :label="__('catalog.name_en')" name="modifierNameEn" wire:model="modifierNameEn" required />
    <x-field.input :label="__('catalog.name_ar')" name="modifierNameAr" wire:model="modifierNameAr" dir="rtl" />
    <x-field.input type="number" :label="__('catalog.price')" name="modifierPriceInput" wire:model="modifierPriceInput" required inputmode="decimal" />
    <x-field.input type="number" :label="__('catalog.vat_rate')" name="modifierVatRate" wire:model="modifierVatRate" inputmode="decimal" />
    <x-field.toggle wire:model="modifierActive" name="modifierActive" :label="__('catalog.active')" />
</div>