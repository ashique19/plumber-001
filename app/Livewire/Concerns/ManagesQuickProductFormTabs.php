<?php

namespace App\Livewire\Concerns;

use App\Support\Catalog\CatalogI18n;
use Illuminate\Validation\ValidationException;

trait ManagesQuickProductFormTabs
{
    /** @var 'basic'|'details' */
    public string $quickProductFormTab = 'basic';

    public bool $quickProductActive = true;

    public string $quickProductNameBn = '';

    public function setQuickProductFormTab(string $tab): void
    {
        if (in_array($tab, ['basic', 'details'], true)) {
            $this->quickProductFormTab = $tab;
        }
    }

    public function goToQuickProductDetailsTab(): void
    {
        $rules = $this->quickProductRulesForTab('basic');
        if ($rules === []) {
            $this->quickProductFormTab = 'details';

            return;
        }

        try {
            $this->validate($rules, $this->quickProductImageValidationMessages());
        } catch (ValidationException $e) {
            $this->focusQuickProductTabForErrors(array_keys($e->validator->errors()->messages()));
            $this->toastError(__('catalog.modal_error_summary'), persistent: true);

            throw $e;
        }

        $this->quickProductFormTab = 'details';
    }

    /**
     * @return array{basic: int, details: int}
     */
    public function quickProductFormTabErrorCounts(): array
    {
        $counts = ['basic' => 0, 'details' => 0];

        foreach ($this->getErrorBag()->getMessages() as $field => $messages) {
            $tab = $this->quickProductTabForField((string) $field);
            if ($tab !== null) {
                $counts[$tab] += count($messages);
            }
        }

        return $counts;
    }

  /**
   * @param  list<string>  $errorKeys
   */
    protected function focusQuickProductTabForErrors(array $errorKeys): void
    {
        foreach (['basic', 'details'] as $tab) {
            foreach ($errorKeys as $key) {
                if ($this->quickProductTabForField($key) === $tab) {
                    $this->quickProductFormTab = $tab;

                    return;
                }
            }
        }
    }

    protected function quickProductTabForField(string $field): ?string
    {
        $field = explode('.', $field)[0];

        $basic = [
            'quickProductNameEn', 'quickProductNameAr', 'quickProductNameBn',
            'quickProductPrice', 'quickProductVat', 'quickProductCategoryId',
            'quickProductItemType', 'quickProductActive', 'quickProductImageUpload',
        ];
        $details = [
            'quickProductSku',
            'quickProductDescriptionEn',
            'quickProductDescriptionAr',
            'quickProductDescriptionBn',
            'quickProductCost',
            'quickProductTrackStock',
            'quickProductOpeningStock',
            'quickProductLowStockThreshold',
        ];

        if (in_array($field, $basic, true)) {
            return 'basic';
        }
        if (in_array($field, $details, true)) {
            return 'details';
        }

        return null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function quickProductRulesForTab(string $tab): array
    {
        return array_filter(
            $this->quickProductValidationRuleSet(),
            fn (string $field): bool => $this->quickProductTabForField($field) === $tab,
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    abstract protected function quickProductValidationRuleSet(): array;

    protected function quickProductCatalogI18nReady(): bool
    {
        return CatalogI18n::columnsExist();
    }
}