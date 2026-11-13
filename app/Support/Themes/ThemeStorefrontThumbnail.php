<?php

namespace App\Support\Themes;

use App\Models\BusinessSetting;

final class ThemeStorefrontThumbnail
{
  public static function urlFor(BusinessSetting $business): ?string
  {
    if (! $business->isCafeTheme()) {
      return null;
    }

    return asset('images/templates/cafe-storefront.webp');
  }

  public static function altFor(BusinessSetting $business, string $locale): string
  {
    $name = $business->displayName($locale);

    return __('invoicing.thumbnail_alt_cafe', ['name' => $name], $locale);
  }

  public static function themeLabel(BusinessSetting $business): string
  {
    return __('invoicing.tenant_theme_cafe');
  }
}
