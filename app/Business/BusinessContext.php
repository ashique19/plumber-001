<?php

namespace App\Business;

use App\Models\BusinessSetting;
use RuntimeException;

class BusinessContext
{
  protected static ?BusinessSetting $business = null;

  public static function set(?BusinessSetting $business): void
  {
    self::$business = $business;
  }

  public static function get(): ?BusinessSetting
  {
    return self::$business;
  }

  public static function require(): BusinessSetting
  {
    $business = self::get();
    if ($business === null) {
      throw new RuntimeException('Business context is not loaded.');
    }

    return $business;
  }

  public static function clear(): void
  {
    self::$business = null;
  }
}
