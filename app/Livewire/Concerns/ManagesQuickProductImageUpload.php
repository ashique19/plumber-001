<?php

namespace App\Livewire\Concerns;

use App\Models\BusinessSetting;
use App\Services\Catalog\ProductImageService;
use App\Support\Upload\ProductImageConstraints;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ManagesQuickProductImageUpload
{
    public $quickProductImageUpload = null;

    public function removeQuickProductImage(): void
    {
        if ($this->quickProductImageUpload instanceof TemporaryUploadedFile) {
            $this->quickProductImageUpload->delete();
        }
        $this->quickProductImageUpload = null;
        $this->resetValidation('quickProductImageUpload');
    }

    /**
     * @return array<string, list<string>>
     */
    protected function quickProductImageValidationRules(): array
    {
        if ($this->quickProductImageUpload === null) {
            return [];
        }

        return [
            'quickProductImageUpload' => ProductImageConstraints::componentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function quickProductImageValidationMessages(): array
    {
        return [
            'quickProductImageUpload.mimes' => __('catalog.product_image.errors.wrong_type'),
            'quickProductImageUpload.max' => __('catalog.product_image.errors.too_large'),
            'quickProductImageUpload.file' => __('catalog.product_image.errors.wrong_type'),
        ];
    }

    protected function persistQuickProductImage(BusinessSetting $business): ?string
    {
        if ($this->quickProductImageUpload === null) {
            return null;
        }

        try {
            return app(ProductImageService::class)->store($business, $this->quickProductImageUpload);
        } catch (\InvalidArgumentException $e) {
            $this->addError('quickProductImageUpload', $e->getMessage());

            throw $e;
        }
    }

    protected function resetQuickProductImageUpload(): void
    {
        if ($this->quickProductImageUpload instanceof TemporaryUploadedFile) {
            $this->quickProductImageUpload->delete();
        }
        $this->quickProductImageUpload = null;
        $this->resetValidation('quickProductImageUpload');
    }

    public function quickProductImagePreviewUrl(): ?string
    {
        if ($this->quickProductImageUpload === null) {
            return null;
        }

        try {
            return $this->quickProductImageUpload->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}