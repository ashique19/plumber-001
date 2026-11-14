<?php

namespace App\Services\Catalog;

use App\Models\BusinessSetting;
use App\Support\Upload\ProductImageConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
    /**
     * Relative path from public/ (asset-ready), e.g. uploads/products/1/abc.webp
     */
    public function store(BusinessSetting $business, UploadedFile $file, ?string $replaceRelative = null): string
    {
        if ($file->getSize() > ProductImageConstraints::MAX_BYTES) {
            throw new \InvalidArgumentException(__('catalog.product_image.errors.too_large'));
        }

        $mime = strtolower((string) $file->getMimeType());
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'], true)) {
            throw new \InvalidArgumentException(__('catalog.product_image.errors.wrong_type'));
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false) {
            throw new \InvalidArgumentException(__('catalog.product_image.errors.wrong_type'));
        }

        [$width, $height] = $info;
        if ($width < ProductImageConstraints::MIN_WIDTH || $height < ProductImageConstraints::MIN_HEIGHT) {
            throw new \InvalidArgumentException(__('catalog.product_image.errors.too_small', [
                'min' => ProductImageConstraints::MIN_WIDTH,
            ]));
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: 'webp');
        if (! in_array($ext, ProductImageConstraints::allowedMimes(), true)) {
            $ext = match ($mime) {
                'image/png' => 'png',
                'image/jpeg', 'image/jpg' => 'jpg',
                default => 'webp',
            };
        }

        $hash = substr(hash('sha256', $file->getRealPath().microtime(true)), 0, 16);
        $filename = 'product-'.$hash.'.'.$ext;

        try {
            $relative = $this->writeToPreferredDisk($business, $filename, $file);
            $this->deleteIfManaged($replaceRelative);

            return $relative;
        } catch (\InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new \InvalidArgumentException(__('catalog.product_image.errors.upload_failed'));
        }
    }

    public function deleteIfManaged(?string $relative): void
    {
        if (! is_string($relative) || $relative === '') {
            return;
        }

        if (str_starts_with($relative, 'uploads/products/')) {
            $absolute = public_path($relative);
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        } elseif (str_starts_with($relative, 'products/')) {
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        }
    }

    private function writeToPreferredDisk(BusinessSetting $business, string $filename, UploadedFile $file): string
    {
        $publicRoot = public_path('uploads/products/'.$business->id);
        if (! is_dir($publicRoot)) {
            File::makeDirectory($publicRoot, 0755, true, true);
        }
        @chmod($publicRoot, 0755);

        $absolute = $publicRoot.DIRECTORY_SEPARATOR.$filename;
        $source = $file->getRealPath();
        if (! is_string($source) || ! @copy($source, $absolute)) {
            $file->move($publicRoot, $filename);
            $absolute = $publicRoot.DIRECTORY_SEPARATOR.$filename;
        }
        @chmod($absolute, 0644);

        return 'uploads/products/'.$business->id.'/'.$filename;
    }
}