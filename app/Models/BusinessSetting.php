<?php

namespace App\Models;

use App\Casts\BusinessSettingsPayload;
use App\Enums\InvoiceDocumentType;
use App\Support\Invoicing\ZHeartCredentials;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class BusinessSetting extends Model
{
  protected $table = 'business_settings';

  protected $fillable = [
    'name',
    'settings',
  ];

  protected function casts(): array
  {
    return [
      'settings' => BusinessSettingsPayload::class,
    ];
  }

  public static function primary(): self
  {
    $byId = self::query()->find(1);
    if ($byId !== null) {
      return $byId;
    }

    $first = self::query()->orderBy('id')->first();
    if ($first !== null) {
      return $first;
    }

    throw new ModelNotFoundException('No business_settings row found.');
  }

  public static function singleton(): self
  {
    return self::primary();
  }

  /**
   * @param  array<string, mixed>  $settings
   */
  public function writeSettings(array $settings, ?string $name = null): void
  {
    json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    if ($this->exists) {
      $this->refresh();
    }

    $payload = ['settings' => $settings];
    if ($name !== null) {
      $payload['name'] = $name;
    }

    $this->forceFill($payload);

    if (! $this->isDirty()) {
      $unchangedSettings = ! self::settingsJsonDiffers($settings, $this->getOriginal('settings'));
      $unchangedName = $name === null || $name === $this->getOriginal('name');

      if ($unchangedSettings && $unchangedName) {
        throw new \RuntimeException('Business settings did not change in memory; database write was skipped.');
      }

      $this->touch();
    }

    if (! $this->save()) {
      throw new \RuntimeException('Failed to persist business settings.');
    }
  }

  /**
   * @param  array<string, mixed>  $next
   */
  private static function settingsJsonDiffers(array $next, mixed $stored): bool
  {
    if ($stored === null) {
      return true;
    }

    $decoded = $stored;
    if (is_string($decoded)) {
      $decoded = json_decode($decoded, true);
      if (! is_array($decoded)) {
        return true;
      }
    }

    if (! is_array($decoded)) {
      return true;
    }

    return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
      !== json_encode($next, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  public static function zheartApiKeyPrefixFromEnv(): ?string
  {
    $key = config('zheart.api_key');
    if (! is_string($key) || $key === '') {
      return null;
    }

    if (preg_match('/^(zh_[a-z0-9]{8})\./', $key, $matches) === 1) {
      return $matches[1].'.…';
    }

    return 'zh_….…';
  }

  public static function defaultSettings(): array
  {
    return [
      'theme' => [
        'layout_key' => 'plumber',
        'logo_url' => null,
        'colors' => [
          'navy' => '#0B1F3A',
          'steel' => '#2D4A6F',
          'sky' => '#E8F1FA',
          'ice' => '#F4F8FC',
          'safety' => '#E87722',
          'accent' => '#1B8A8A',
          'slate' => '#475569',
        ],
        'font' => 'Instrument Sans',
        'font_ar' => 'Tajawal',
      ],
      'default_locale' => 'bn',
      'legal_entity' => [
        'name_ar' => '',
        'name_en' => '',
        'vat_number' => '',
        'cr_number' => '',
        'address' => '',
        'national_address' => [
          'building' => '',
          'street' => '',
          'district' => '',
          'city' => '',
          'postal_code' => '',
          'country' => 'SA',
          'additional' => '',
        ],
      ],
      'invoicing' => [
        'default_document_type' => 'simplified',
        'integration_enabled' => false,
        'payment_terms_days' => 30,
        'invoice_print_template' => 'modern',
        'quotation_print_template' => 'modern',
      ],
      'quotations' => [
        'default_terms' => '',
      ],
      'content' => [
        'plumber' => [],
      ],
      'zheart' => [
        'base_url' => '',
        'api_key_encrypted' => null,
        'api_key_last4' => null,
      ],
    ];
  }

  public function invoicingIntegrationEnabled(): bool
  {
    $stored = data_get($this->settings, 'invoicing.integration_enabled');

    if ($stored === null) {
      return ZHeartCredentials::isConfigured($this);
    }

    return (bool) $stored && ZHeartCredentials::isConfigured($this);
  }

  public function defaultInvoiceDocumentType(): InvoiceDocumentType
  {
    $stored = data_get($this->settings, 'invoicing.default_document_type', 'simplified');

    return (string) $stored === 'simplified'
      ? InvoiceDocumentType::Simplified
      : InvoiceDocumentType::Standard;
  }

  public function alternateInvoiceDocumentType(): InvoiceDocumentType
  {
    return $this->defaultInvoiceDocumentType() === InvoiceDocumentType::Simplified
      ? InvoiceDocumentType::Standard
      : InvoiceDocumentType::Simplified;
  }

  /**
   * @param  array<string, mixed>  $patch
   */
  public function mergeLegalEntity(array $patch): void
  {
    $settings = $this->settings ?? self::defaultSettings();
    $current = (array) ($settings['legal_entity'] ?? self::defaultSettings()['legal_entity']);
    $settings['legal_entity'] = array_replace_recursive($current, $patch);
    $this->forceFill(['settings' => $settings])->save();
  }

  public function themeLayout(): string
  {
    return (string) data_get($this->settings, 'theme.layout_key', 'plumber');
  }

  public function isPlumberTheme(): bool
  {
    return $this->themeLayout() === 'plumber';
  }

  public function hasThemedStorefront(): bool
  {
    return false;
  }

  public function displayName(?string $locale = null): string
  {
    $locale ??= app()->getLocale();
    $legal = data_get($this->settings, 'legal_entity', []);

    if ($locale === 'ar' && filled($legal['name_ar'] ?? null)) {
      return (string) $legal['name_ar'];
    }

    if (filled($legal['name_en'] ?? null)) {
      return (string) $legal['name_en'];
    }

    return $this->name;
  }

  public function thumbnailAccentColor(): string
  {
    $colors = $this->themeColors();

    return (string) ($colors['navy'] ?? $colors['steel'] ?? '#0B1F3A');
  }

  public function thumbnailSurfaceColor(): string
  {
    $colors = $this->themeColors();

    return (string) ($colors['ice'] ?? $colors['sky'] ?? '#F4F8FC');
  }

  /**
   * @return array<string, mixed>
   */
  public function plumberContent(): array
  {
    $stored = (array) data_get($this->settings, 'content.plumber', []);

    return \App\Support\Storefront\PlumberStorefrontContent::enrich($this, $stored);
  }

  /**
   * @return array<string, string>
   */
  public function themeColors(): array
  {
    return (array) data_get($this->settings, 'theme.colors', []);
  }
}
