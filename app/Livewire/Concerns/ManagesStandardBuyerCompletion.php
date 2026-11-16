<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Invoicing\StandardBuyerFieldGaps;
use App\Support\Validation\FieldValidationRules;

trait ManagesStandardBuyerCompletion
{
    public bool $showStandardBuyerCompletionModal = false;

    public bool $standardBuyerPersistToCustomer = true;

    public bool $resumeIssueAfterBuyerCompletion = false;

    /** @var list<string> */
    public array $standardBuyerCompletionFieldKeys = [];

    /**
     * @return list<string>
     */
    public function standardBuyerCompletionMissingKeys(): array
    {
        if (! $this->documentStandardBuyerRequired()) {
            return [];
        }

        if ($this->showStandardBuyerCompletionModal && $this->standardBuyerCompletionFieldKeys !== []) {
            return $this->standardBuyerCompletionFieldKeys;
        }

        return StandardBuyerFieldGaps::missingKeys($this);
    }

    protected function promptStandardBuyerCompletionIfNeeded(bool $resumeIssue = false): bool
    {
        if (! $this->documentStandardBuyerRequired()) {
            return true;
        }

        if ($this->selectedCustomerId === null) {
            return true;
        }

        if (! StandardBuyerFieldGaps::hasGaps($this)) {
            return true;
        }

        $this->resumeIssueAfterBuyerCompletion = $resumeIssue;
        $this->standardBuyerPersistToCustomer = true;
        $this->standardBuyerCompletionFieldKeys = StandardBuyerFieldGaps::missingKeys($this);
        $this->showStandardBuyerCompletionModal = true;

        return false;
    }

    public function cancelStandardBuyerCompletion(): void
    {
        $this->showStandardBuyerCompletionModal = false;
        $this->resumeIssueAfterBuyerCompletion = false;
        $this->standardBuyerCompletionFieldKeys = [];
    }

    public function confirmStandardBuyerCompletion(LoyaltyService $loyalty): void
    {
        if (! $this->documentStandardBuyerRequired() || $this->selectedCustomerId === null) {
            $this->cancelStandardBuyerCompletion();

            return;
        }

        $missing = $this->standardBuyerCompletionFieldKeys;
        if ($missing === []) {
            $this->closeStandardBuyerCompletionAndResume();

            return;
        }

        $rules = $this->standardBuyerCompletionRules($missing);
        $this->validate($rules);

        if ($this->standardBuyerPersistToCustomer) {
            $this->persistStandardBuyerFieldsToCustomer($loyalty);
        }

        $this->closeStandardBuyerCompletionAndResume();
    }

    protected function closeStandardBuyerCompletionAndResume(): void
    {
        $shouldResume = $this->resumeIssueAfterBuyerCompletion;
        $this->showStandardBuyerCompletionModal = false;
        $this->resumeIssueAfterBuyerCompletion = false;
        $this->standardBuyerCompletionFieldKeys = [];

        if ($shouldResume) {
            $this->afterStandardBuyerCompletionConfirmed();
        }
    }

    protected function afterStandardBuyerCompletionConfirmed(): void
    {
    }

    /**
     * @param  list<string>  $missing
     * @return array<string, mixed>
     */
    protected function standardBuyerCompletionRules(array $missing): array
    {
        $rules = [];

        if (in_array('vat_or_cr', $missing, true)) {
            $rules['buyerVat'] = array_merge(FieldValidationRules::vatOptional(), ['required_without:buyerCr']);
            $rules['buyerCr'] = array_merge(FieldValidationRules::crNumberOptional(), ['required_without:buyerVat']);
        }

        if (in_array('addrBuilding', $missing, true)) {
            $rules['addrBuilding'] = ['required', 'string', 'max:32'];
        }
        if (in_array('addrStreet', $missing, true)) {
            $rules['addrStreet'] = ['required', 'string', 'max:120'];
        }
        if (in_array('addrCity', $missing, true)) {
            $rules['addrCity'] = ['required', 'string', 'max:80'];
        }
        if (in_array('addrPostal', $missing, true)) {
            $rules['addrPostal'] = FieldValidationRules::postalCodeRequired();
        }

        if (in_array('addrDistrict', $missing, true)) {
            $rules['addrDistrict'] = ['nullable', 'string', 'max:120'];
        }
        if (in_array('buyerCountry', $missing, true)) {
            $rules['buyerCountry'] = ['required', 'string', 'size:2'];
        }

        return $rules;
    }

    protected function persistStandardBuyerFieldsToCustomer(LoyaltyService $loyalty): void
    {
        $customerId = $this->selectedCustomerId;
        if ($customerId === null) {
            return;
        }

        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        $vatDigits = preg_replace('/\D/', '', $this->buyerVat) ?? '';
        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = (array) data_get($profile, 'national_address', []);

        $customer->forceFill([
            'vat_number' => $vatDigits !== '' ? $vatDigits : $customer->vat_number,
            'profile' => array_merge($profile, [
                'cr_number' => trim($this->buyerCr) !== '' ? trim($this->buyerCr) : (string) data_get($profile, 'cr_number', ''),
                'country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
                'national_address' => array_merge($na, [
                    'building' => trim($this->addrBuilding) !== '' ? trim($this->addrBuilding) : ($na['building'] ?? ''),
                    'street' => trim($this->addrStreet) !== '' ? trim($this->addrStreet) : ($na['street'] ?? ''),
                    'district' => trim($this->addrDistrict) !== '' ? trim($this->addrDistrict) : ($na['district'] ?? ''),
                    'city' => trim($this->addrCity) !== '' ? trim($this->addrCity) : ($na['city'] ?? 'Riyadh'),
                    'postal_code' => trim($this->addrPostal) !== '' ? trim($this->addrPostal) : ($na['postal_code'] ?? $na['postal'] ?? ''),
                    'additional' => trim($this->addrAdditional) !== '' ? trim($this->addrAdditional) : ($na['additional'] ?? ''),
                    'country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
                ]),
            ]),
        ])->save();

        $loyalty->ensureReferralCode($customer);
    }
}