<?php

namespace App\Support\Invoicing;

use App\Support\EnabledLocales;

final class PrintBodyLocale
{
  /**
   * Print/PDF bodies use English UI strings plus embedded Arabic copy — never Bangla or other admin UI locales.
   */
  public static function resolveFromRequest(?string $requested): string
  {
    $locale = EnabledLocales::resolve($requested ?? 'en');

    return in_array($locale, ['en', 'ar'], true) ? $locale : 'en';
  }
}