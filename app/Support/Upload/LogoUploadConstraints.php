<?php

namespace App\Support\Upload;

final class LogoUploadConstraints
{
    /** Final stored logo — 1024 KB safety net (client targets ~100 KB). */
    public const MAX_BYTES = 1_048_576;

    /** Livewire temporary upload limit (kilobytes). */
    public const TEMP_MAX_KILOBYTES = 1024;

    /** Client-side fit box (browser); documented for support. */
    public const CLIENT_MAX_BOX = 256;

    public const MIN_WIDTH = 64;

    public const MIN_HEIGHT = 64;

    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return ['png', 'jpg', 'jpeg', 'webp', 'svg'];
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
            'logoUpload.mimes' => __('business.logo_upload.errors.mimes'),
            'logoUpload.max' => __('business.logo_upload.errors.too_big', ['limit' => '1 MB']),
            'logoUpload.file' => __('business.logo_upload.errors.invalid_type'),
            'logoUpload.image' => __('business.logo_upload.errors.invalid_type'),
        ];
    }
}
