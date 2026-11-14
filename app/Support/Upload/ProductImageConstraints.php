<?php

namespace App\Support\Upload;

final class ProductImageConstraints
{
    /** Stored file cap (client targets ~800px box). */
    public const MAX_BYTES = 2_097_152;

    /** Livewire temporary upload limit (kilobytes). */
    public const TEMP_MAX_KILOBYTES = 2048;

    public const CLIENT_MAX_EDGE = 800;

    public const MIN_WIDTH = 64;

    public const MIN_HEIGHT = 64;

    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return ['png', 'jpg', 'jpeg', 'webp'];
    }

    /**
     * @return list<string>
     */
    public static function livewireTempRules(): array
    {
        return [
            'file',
            'mimes:'.implode(',', self::allowedMimes()),
            'max:'.self::TEMP_MAX_KILOBYTES,
        ];
    }

    /**
     * @return list<string>
     */
    public static function componentRules(): array
    {
        return [
            'nullable',
            'file',
            'mimes:'.implode(',', self::allowedMimes()),
            'max:'.(int) (self::MAX_BYTES / 1024),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'productImageUpload.mimes' => __('catalog.product_image.errors.wrong_type'),
            'productImageUpload.max' => __('catalog.product_image.errors.too_large'),
            'productImageUpload.file' => __('catalog.product_image.errors.wrong_type'),
            'productImageUpload.image' => __('catalog.product_image.errors.wrong_type'),
        ];
    }
}