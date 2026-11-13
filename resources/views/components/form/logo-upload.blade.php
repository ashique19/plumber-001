@props([
    'label',
    'wireModel' => 'logoUpload',
    'previewUrl' => null,
    'removeAction' => 'removeLogo',
    'awaitingSave' => false,
])

@php
    $initialPreview = $previewUrl ? asset($previewUrl) : '';
    $logoLabels = [
        'choose' => __('business.brand.logo_choose'),
        'replace' => __('business.logo_upload.replace'),
        'remove' => __('business.brand.logo_remove'),
        'remove_pending' => __('business.logo_upload.remove_pending'),
        'uploading' => __('business.logo_upload.uploading'),
        'ready_save' => __('business.logo_upload.ready_save'),
        'uploaded_ok' => __('business.logo_upload.uploaded_ok'),
        'current_logo' => __('business.logo_upload.current_logo'),
        'no_logo' => __('business.logo_upload.no_logo'),
        'invalid_type' => __('business.logo_upload.errors.invalid_type'),
        'too_small' => __('business.logo_upload.errors.too_small', ['min' => 64]),
        'svg_unsafe' => __('business.logo_upload.errors.svg_unsafe'),
        'processing_failed' => __('business.logo_upload.errors.processing_failed'),
        'still_too_large' => __('business.logo_upload.errors.still_too_large'),
        'failed_generic' => __('business.logo_upload.errors.failed_generic'),
        'failed_causes' => __('business.logo_upload.errors.failed_causes'),
    ];
@endphp

<div {{ $attributes->class(['form-field']) }}>
    <span class="form-field__label">{{ $label }}</span>
    <span class="form-field__hint">{{ __('business.brand.logo_hint') }}</span>

    <div
        class="form-file-upload form-logo-upload"
        x-data="adminLogoUpload({
            wireProp: @js($wireModel),
            initialPreview: @js($initialPreview),
            labels: @js($logoLabels),
        })"
    >
        <div class="form-logo-upload__preview" x-show="hasPreview || showMeta" x-cloak>
            <div class="form-logo-upload__thumb-wrap form-file-upload__thumb form-file-upload__thumb--alpha" x-show="hasPreview" x-cloak>
                <img :src="previewUrl" alt="" class="form-logo-upload__thumb-img" width="64" height="64" loading="lazy" decoding="async">
            </div>
            <div
                class="form-logo-upload__thumb-wrap form-file-upload__thumb form-logo-upload__placeholder"
                x-show="!hasPreview && showMeta"
                x-cloak
                aria-hidden="true"
            ></div>
            <div class="form-logo-upload__meta min-w-0">
                <p class="form-logo-upload__filename" x-show="filenameText()" x-text="filenameText()"></p>
                <p class="form-logo-upload__size" x-show="metaLabel" x-text="metaLabel"></p>
            </div>
        </div>

        <div class="form-logo-upload__empty" x-show="!hasPreview && !showMeta" x-cloak>
            <span class="form-logo-upload__empty-label">{{ __('business.logo_upload.no_logo') }}</span>
        </div>

        @if ($awaitingSave)
            <p class="form-logo-upload__ready" wire:loading.remove wire:target="{{ $wireModel }}">
                {{ __('business.logo_upload.ready_save') }}
            </p>
        @endif

        <div
            class="form-logo-upload__progress"
            x-show="uploadInFlight"
            x-cloak
            role="progressbar"
            :aria-valuenow="progress"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-live="polite"
        >
            <div class="form-logo-upload__progress-track">
                <span class="form-logo-upload__progress-bar" :style="`width: ${Math.max(progress, 8)}%`"></span>
            </div>
            <span class="form-logo-upload__progress-label" x-text="labels.uploading + ' ' + progress + '%'"></span>
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
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                @change="pickFile($event)"
            />
            <button type="button" class="tap-action-muted min-h-11 px-3 text-sm font-semibold" @click="openPicker()">
                <span x-text="hasPreview || storedPreview ? labels.replace : labels.choose"></span>
            </button>
            <button
                type="button"
                class="tap-action-muted min-h-11 px-3 text-sm font-semibold"
                x-show="fileName || @js($awaitingSave)"
                @click="clearPending()"
                x-cloak
            >
                <span x-text="labels.remove_pending"></span>
            </button>
            @if ($removeAction && $previewUrl)
                <button type="button" class="tap-action-muted min-h-11 px-3 text-sm font-semibold" wire:click="{{ $removeAction }}">
                    {{ $logoLabels['remove'] }}
                </button>
            @endif
        </div>
    </div>

    @if ($previewUrl)
        <p class="form-field__hint mt-1">
            {{ __('business.logo_upload.stored_at') }}
            <a href="{{ asset($previewUrl) }}" class="font-semibold text-[var(--cafe-caramel)] underline" target="_blank" rel="noopener noreferrer">{{ asset($previewUrl) }}</a>
        </p>
    @endif
</div>
