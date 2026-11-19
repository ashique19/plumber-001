@props([
    'label',
    'wireModel' => 'productImageUpload',
    'previewUrl' => null,
    'removeAction' => 'removeProductImage',
])

@php
    $initialPreview = $previewUrl
        ? (str_starts_with((string) $previewUrl, 'http') ? $previewUrl : asset($previewUrl))
        : '';
    $imageLabels = [
        'choose' => __('catalog.product_image.choose'),
        'replace' => __('catalog.product_image.replace'),
        'remove' => __('catalog.product_image.remove'),
        'remove_pending' => __('catalog.product_image.remove_pending'),
        'uploading' => __('catalog.product_image.uploading'),
        'crop_title' => __('catalog.product_image.crop_title'),
        'crop_confirm' => __('catalog.product_image.crop_confirm'),
        'crop_cancel' => __('catalog.product_image.crop_cancel'),
        'wrong_type' => __('catalog.product_image.errors.wrong_type'),
        'too_large' => __('catalog.product_image.errors.too_large'),
        'too_small' => __('catalog.product_image.errors.too_small', ['min' => 64]),
        'upload_failed' => __('catalog.product_image.errors.upload_failed'),
        'network' => __('catalog.product_image.errors.network'),
        'processing_failed' => __('catalog.product_image.errors.processing_failed'),
    ];
@endphp

<div {{ $attributes->class(['form-field', 'sm:col-span-2']) }}>
    <span class="form-field__label">{{ $label }}</span>
    <span class="form-field__hint">{{ __('catalog.product_image.hint') }}</span>

    <div
        class="form-file-upload form-logo-upload form-product-image-upload"
        x-data="catalogProductImageUpload({
            wireProp: @js($wireModel),
            initialPreview: @js($initialPreview),
            labels: @js($imageLabels),
        })"
    >
        <div class="form-logo-upload__preview" x-show="hasPreview || showMeta" x-cloak>
            <div class="form-logo-upload__thumb-wrap form-file-upload__thumb" x-show="hasPreview" x-cloak>
                <img :src="previewUrl" alt="" class="form-product-image-upload__thumb-img" width="96" height="96" loading="lazy" decoding="async">
            </div>
            <div class="form-logo-upload__meta min-w-0">
                <p class="form-logo-upload__filename" x-show="fileName" x-text="fileName"></p>
                <p class="form-logo-upload__size" x-show="metaLabel" x-text="metaLabel"></p>
            </div>
        </div>

        <div class="form-logo-upload__empty" x-show="!hasPreview && !showMeta" x-cloak>
            <span class="form-logo-upload__empty-label">{{ __('catalog.product_image.no_image') }}</span>
        </div>

        <div
            class="form-logo-upload__progress"
            x-show="uploadInFlight || preparing"
            x-cloak
            role="progressbar"
            :aria-valuenow="progress"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-live="polite"
        >
            <div class="form-logo-upload__progress-track">
                <span class="form-logo-upload__progress-bar" :style="`width: ${Math.max(progress, preparing ? 12 : 8)}%`"></span>
            </div>
            <span
                class="form-logo-upload__progress-label"
                x-text="preparing && !uploadInFlight ? labels.crop_title : (labels.uploading + ' ' + progress + '%')"
            ></span>
        </div>

        <p class="form-field__error" role="alert" x-show="error" x-text="error" x-cloak></p>

        @error($wireModel)
            <span class="form-field__error" role="alert">{{ $message }}</span>
        @enderror

        <div class="form-logo-upload__actions">
            <input
                type="file"
                x-ref="fileInput"
                class="sr-only"
                accept="image/png,image/jpeg,image/webp"
                capture="environment"
                @change="pickFile($event)"
            />
            <button type="button" class="tap-action-muted min-h-11 px-3 text-sm font-semibold" @click="openPicker()">
                <span x-text="hasPreview || storedPreview ? labels.replace : labels.choose"></span>
            </button>
            <button
                type="button"
                class="tap-action-muted min-h-11 px-3 text-sm font-semibold"
                x-show="fileName"
                @click="clearPending()"
                x-cloak
            >
                <span x-text="labels.remove_pending"></span>
            </button>
            @if ($previewUrl)
                <button type="button" class="tap-action-muted min-h-11 px-3 text-sm font-semibold" @click="removeStored()">
                    {{ $imageLabels['remove'] }}
                </button>
            @endif
        </div>

        <div
            class="form-product-image-crop"
            x-show="cropOpen"
            x-cloak
            role="dialog"
            aria-modal="true"
            :aria-label="labels.crop_title"
        >
            <div class="form-product-image-crop__panel">
                <p class="form-product-image-crop__title" x-text="labels.crop_title"></p>
                <div class="form-product-image-crop__stage">
                    <img x-ref="cropImage" alt="" class="form-product-image-crop__img" />
                </div>
                <div class="form-product-image-crop__actions">
                    <button type="button" class="tap-action-muted min-h-11 flex-1 text-sm font-semibold" @click="cancelCrop()">
                        <span x-text="labels.crop_cancel"></span>
                    </button>
                    <button type="button" class="tap-action-primary min-h-11 flex-1 text-sm font-semibold" @click="confirmCrop()">
                        <span x-text="labels.crop_confirm"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>