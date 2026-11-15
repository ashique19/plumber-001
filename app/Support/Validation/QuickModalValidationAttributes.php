<?php

namespace App\Support\Validation;

/**
 * Human-readable validation attribute names for quick-add modal fields.
 */
final class QuickModalValidationAttributes
{
    /**
     * @return array<string, string>
     */
    public static function map(): array
    {
        return [
            'customerQuickName' => __('validation.quick_attributes.customer_name'),
            'customerQuickNameAr' => __('validation.quick_attributes.customer_name_ar'),
            'customerQuickPhone' => __('validation.quick_attributes.customer_phone'),
            'customerQuickEmail' => __('validation.quick_attributes.customer_email'),
            'customerQuickVat' => __('validation.quick_attributes.customer_vat'),
            'customerQuickCr' => __('validation.quick_attributes.customer_cr'),
            'customerQuickCountry' => __('validation.quick_attributes.customer_country'),
            'customerQuickAddrBuilding' => __('validation.quick_attributes.building'),
            'customerQuickAddrStreet' => __('validation.quick_attributes.street'),
            'customerQuickAddrDistrict' => __('validation.quick_attributes.district'),
            'customerQuickAddrCity' => __('validation.quick_attributes.city'),
            'customerQuickAddrPostal' => __('validation.quick_attributes.postal'),
            'customerQuickAddrAdditional' => __('validation.quick_attributes.additional'),

            'quickProductNameEn' => __('validation.quick_attributes.product_name_en'),
            'quickProductNameAr' => __('validation.quick_attributes.product_name_ar'),
            'quickProductPrice' => __('validation.quick_attributes.product_price'),
            'quickProductVat' => __('validation.quick_attributes.product_vat'),
            'quickProductItemType' => __('validation.quick_attributes.product_item_type'),
            'quickProductCategoryId' => __('validation.quick_attributes.product_category'),
            'quickProductTrackStock' => __('validation.quick_attributes.product_track_stock'),

            'quickSupplierName' => __('validation.quick_attributes.supplier_name'),
            'quickSupplierNameAr' => __('validation.quick_attributes.supplier_name_ar'),
            'quickSupplierPhone' => __('validation.quick_attributes.supplier_phone'),
            'quickSupplierVat' => __('validation.quick_attributes.supplier_vat'),
        ];
    }
}