<?php

namespace App\Models;

use App\Enums\InvoiceDocumentType;
use Illuminate\Database\Eloquent\Model;

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
      'settings' => 'array',
    ];
  }

  public static function singleton(): self
  {
    return self::query()->findOrFail(1);
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
      'default_locale' => 'ar',
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
          'additional' => '',
        ],
      ],
      'invoicing' => [
        'default_document_type' => 'simplified',
        'integration_enabled' => false,
      ],
      'content' => [
        'plumber' => [],
      ],
    ];
  }

  public function invoicingIntegrationEnabled(): bool
  {
    $stored = data_get($this->settings, 'invoicing.integration_enabled');

    if ($stored === null) {
      $key = config('zheart.api_key');

      return is_string($key) && $key !== '';
    }

    return (bool) $stored;
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
