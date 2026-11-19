<div class="catalog-entity-form-fields admin-modal-field-grid admin-modal-field-grid--3" data-testid="catalog-category-form-fields">
    <x-field.input :label="__('catalog.name_en')" name="categoryNameEn" wire:model="categoryNameEn" required />
    <x-field.input :label="__('catalog.name_ar')" name="categoryNameAr" wire:model="categoryNameAr" dir="rtl" />
    @if($categoryBanglaFieldsReady ?? false)
        <x-field.input :label="__('catalog.name_bn')" name="categoryNameBn" wire:model="categoryNameBn" />
    @endif
    @if($categoryDescriptionFieldsReady ?? false)
        <x-field.textarea :label="__('catalog.description_en')" name="categoryDescriptionEn" wire:model="categoryDescriptionEn" rows="3" />
        <x-field.textarea :label="__('catalog.description_ar')" name="categoryDescriptionAr" wire:model="categoryDescriptionAr" dir="rtl" rows="3" />
        <x-field.textarea :label="__('catalog.description_bn')" name="categoryDescriptionBn" wire:model="categoryDescriptionBn" wrapper-class="admin-modal-span-3" rows="3" />
    @endif
    @if (\App\Support\Catalog\ProductCategorySchema::hasIconKeyColumn())
        <x-catalog.category-icon-picker :category-icon-key="$categoryIconKey" />
    @endif
    <x-field.toggle wire:model="categoryActive" name="categoryActive" :label="__('catalog.active')" />
</div>