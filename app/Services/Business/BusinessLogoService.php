<?php

namespace App\Services\Business;

use App\Models\BusinessSetting;
use App\Support\Upload\LogoUploadConstraints;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
class BusinessLogoService
{
  /**
   * Store under public/img/tenants/{id}/ (no storage symlink); path is relative to public/.
   */
  public function store(BusinessSetting $tenant, UploadedFile $file): string
  {
    if ($file->getSize() > LogoUploadConstraints::MAX_BYTES) {
      throw new \InvalidArgumentException(__('business.logo_upload.errors.too_big', ['limit' => '1 MB']));
    }

    try {
      $directory = public_path('img/tenants/'.$tenant->id);
      if (! is_dir($directory)) {
        File::makeDirectory($directory, 0755, true, true);
      }
      @chmod($directory, 0755);

      $mime = strtolower((string) $file->getMimeType());
      $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
      $hash = substr(hash('sha256', $file->getRealPath().microtime(true)), 0, 12);

      if ($mime === 'image/svg+xml' || $ext === 'svg') {
        $filename = 'logo-'.$hash.'.svg';
        $absolute = $directory.DIRECTORY_SEPARATOR.$filename;
        $svg = file_get_contents($file->getRealPath());
        $svg = $this->sanitizeSvg(is_string($svg) ? $svg : '');
        File::put($absolute, $svg);
        @chmod($absolute, 0644);
        $this->writeFaviconFromRaster(null, $directory);

        return 'img/tenants/'.$tenant->id.'/'.$filename;
      }

      $info = @getimagesize($file->getRealPath());
      if ($info !== false) {
        [$w, $h] = $info;
        if ($w < LogoUploadConstraints::MIN_WIDTH || $h < LogoUploadConstraints::MIN_HEIGHT) {
          throw new \InvalidArgumentException(__('business.logo_upload.errors.too_small', [
            'min' => LogoUploadConstraints::MIN_WIDTH,
          ]));
        }
      }

      if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        $ext = match ($mime) {
          'image/webp' => 'webp',
          'image/png' => 'png',
          default => 'webp',
        };
      }

      $filename = 'logo-'.$hash.'.'.$ext;
      $absolute = $directory.DIRECTORY_SEPARATOR.$filename;
      $source = $file->getRealPath();
      if (! is_string($source) || ! @copy($source, $absolute)) {
        $file->move($directory, $filename);
        $absolute = $directory.DIRECTORY_SEPARATOR.$filename;
      }
      @chmod($absolute, 0644);
      $this->writeFaviconFromRaster($absolute, $directory);

      return 'img/tenants/'.$tenant->id.'/'.$filename;
    } catch (\InvalidArgumentException $e) {
      throw $e;
    } catch (\Throwable $e) {
      report($e);
      throw new \InvalidArgumentException(__('business.logo_upload.errors.store_failed'));
    }
  }

  public function deleteTenantLogos(BusinessSetting $tenant, ?string $keepRelative = null): void
  {
    $directory = public_path('img/tenants/'.$tenant->id);
    if (! is_dir($directory)) {
      return;
    }

    $keep = $keepRelative ? basename($keepRelative) : null;
    foreach (glob($directory.'/logo-*') ?: [] as $path) {
      if ($keep !== null && basename($path) === $keep) {
        continue;
      }
      if (is_file($path)) {
        @unlink($path);
      }
    }
  }

  private function sanitizeSvg(string $svg): string
  {
    $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? $svg;
    $svg = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? $svg;
    $svg = preg_replace('/xlink:href\s*=\s*"(?!#)[^"]*"/i', '', $svg) ?? $svg;

    return trim($svg);
  }

  private function writeFaviconFromRaster(?string $source, string $dir): void
  {
    $favicon = $dir.DIRECTORY_SEPARATOR.'favicon.png';
    if ($source !== null && is_file($source) && extension_loaded('gd')) {
      $info = @getimagesize($source);
      if ($info !== false) {
        $src = match ($info[2]) {
          IMAGETYPE_PNG => imagecreatefrompng($source),
          IMAGETYPE_JPEG => imagecreatefromjpeg($source),
          IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($source) : false,
          default => false,
        };
        if ($src !== false) {
          $size = 32;
          $dst = imagecreatetruecolor($size, $size);
          imagealphablending($dst, false);
          imagesavealpha($dst, true);
          imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));
          imagepng($dst, $favicon);
          @chmod($favicon, 0644);
          imagedestroy($src);
          imagedestroy($dst);
        }
      }
    }
  }
}
