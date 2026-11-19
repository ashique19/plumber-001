@php
    $productTabErrors = $productFormTabErrorCounts ?? ['basic' => 0, 'details' => 0];
@endphp

@include('components.catalog.partials.product-modal-body', [
    'variant' => 'catalog',
    'formTab' => $productFormTab,
    'tabErrorCounts' => $productTabErrors,
    'setTabMethod' => 'setProductFormTab',
    'nameEn' => 'productNameEn',
    'nameAr' => 'productNameAr',
    'nameBn' => 'productNameBn',
    'priceModel' => 'productPriceInput',
    'vatModel' => 'productVatRate',
    'categoryModel' => 'productCategoryId',
    'itemTypeModel' => 'productItemType',
    'activeModel' => 'productActive',
    'imageUploadModel' => 'productImageUpload',
    'imageRemoveAction' => 'removeProductImage',
    'imagePreviewUrl' => $productImageRemove ? null : $productExistingImage,
    'priceMode' => $priceMode ?? 'ex',
    'categories' => $categories,
    'catalogI18nReady' => $catalogI18nReady ?? false,
    'catalogUnitsSizesReady' => $catalogUnitsSizesReady ?? false,
    'productStockReady' => $productStockReady ?? false,
    'modifiersFeatureEnabled' => $modifiersFeatureEnabled ?? false,
    'modifiers' => $modifiers ?? collect(),
    'activeUnitsForPicker' => $activeUnitsForPicker ?? collect(),
    'activeSizesForPicker' => $activeSizesForPicker ?? collect(),
    'vatZatcaCategoryOptions' => $vatZatcaCategoryOptions ?? [],
    'editingProductId' => $editingProductId ?? null,
    'productTrackStock' => $productTrackStock ?? false,
])