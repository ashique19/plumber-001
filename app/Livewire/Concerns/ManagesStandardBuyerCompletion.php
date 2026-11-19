<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Customers\CustomerIdentityPersistence;
use App\Support\Customers\CustomerIdentityRules;
use App\Support\Invoicing\InvoicingCustomerLookup;
use App\Support\Invoicing\StandardBuyerFieldGaps;

trait ManagesStandardBuyerCompletion
{
    public bool $showStandardBuyerCompletionModal = false;

    public bool $standardBuyerPersistToCustomer = true;

    public bool $resumeIssueAfterBuyerCompletion = false;

    /** @var list<string> */
    public array $standardBuyerCompletionFieldKeys = [];

    public bool $standardBuyerShowMoreAddress = false;

    /**
     * @return list<string>
     */
    public function standardBuyerMissingKeys(): array
    {
        return $this->standardBuyerCompletionMissingKeys();
    }

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

    public function updatedBuyerVatRegistered(mixed $value): void
    {
        if (property_exists($this, 'buyerVatRegistered')) {
            $this->buyerVatRegistered = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
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
        } else {
            $cr = strtoupper(trim($this->buyerAltIdType ?? '')) === 'CRN'
                ? trim($this->buyerAltIdNumber ?? '')
                : '';
            if (property_exists($this, 'buyerCr') && $cr !== '') {
                $this->buyerCr = $cr;
            }
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
        $buyerFields = CustomerIdentityRules::buyerFields();

        if (in_array('buyer_identity', $missing, true)) {
            $identityRules = CustomerIdentityRules::forHost($this, $buyerFields, [
                'sa_standard_buyer' => true,
            ]);
            $rules = array_merge($rules, array_intersect_key(
                $identityRules,
                array_flip([
                    $buyerFields['vat_registered'],
                    $buyerFields['vat_number'],
                    $buyerFields['alt_id_type'],
                    $buyerFields['alt_id_number'],
                ])
            ));
        }

        if (in_array('addrBuilding', $missing, true)) {
            $rules['addrBuilding'] = ['required', 'string', 'max:120'];
        }
        if (in_array('addrStreet', $missing, true)) {
            $rules['addrStreet'] = ['required', 'string', 'max:120'];
        }
        if (in_array('addrCity', $missing, true)) {
            $rules['addrCity'] = ['required', 'string', 'max:80'];
        }
        if (in_array('addrPostal', $missing, true)) {
            $rules['addrPostal'] = \App\Support\Validation\FieldValidationRules::postalCodeRequired();
        }

        if (in_array('addrDistrict', $missing, true)) {
            $rules['addrDistrict'] = ['required', 'string', 'max:120'];
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

        CustomerIdentityPersistence::applyToCustomer(
            $customer,
            CustomerIdentityPersistence::stateFromHost($this, CustomerIdentityRules::buyerFields()),
        );
        $customer->save();

        $loyalty->ensureReferralCode($customer);
        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer->fresh());
    }
}