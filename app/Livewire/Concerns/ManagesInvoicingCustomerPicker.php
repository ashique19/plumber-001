<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Customers\CustomerIdentityPersistence;
use App\Support\Customers\CustomerIdentityRules;
use App\Support\Invoicing\InvoicingCustomerLookup;
use Illuminate\Support\Collection;

trait ManagesInvoicingCustomerPicker
{
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showQuickCustomerModal = false;

    public bool $customerQuickEditMode = false;

    public string $customerQuickNameAr = '';

    public string $customerQuickName = '';

    public bool $customerQuickVatRegistered = false;

    public string $customerQuickVat = '';

    public string $customerQuickAltIdType = '';

    public string $customerQuickAltIdNumber = '';

    public string $customerQuickPhone = '';

    public string $customerQuickEmail = '';

    public string $customerQuickCountry = 'SA';

    public string $customerQuickAddrBuilding = '';

    public string $customerQuickAddrStreet = '';

    public string $customerQuickAddrDistrict = '';

    public string $customerQuickAddrCity = 'Riyadh';

    public string $customerQuickAddrPostal = '';

    public string $customerQuickAddrAdditional = '';

    public bool $customerQuickShowMoreAddress = false;

    public function updatedCustomerQuickVatRegistered(mixed $value): void
    {
        $this->customerQuickVatRegistered = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function updatedCustomerSearch(string $value): void
    {
        if (trim($value) !== '' && property_exists($this, 'addLineProductSearch')) {
            $this->addLineProductSearch = '';
        }
    }

    public function customerSearchResults(): Collection
    {
        return InvoicingCustomerLookup::search($this->customerSearch);
    }

    public function canCreateCustomersFromDocument(): bool
    {
        return auth('staff')->user()?->can('reports.view') ?? false;
    }

    public function clearCustomerPicker(): void
    {
        if ($this->selectedCustomerId !== null) {
            $this->clearSelectedCustomer();

            return;
        }

        $this->customerSearch = '';
    }

    public function clearSelectedCustomer(): void
    {
        InvoicingCustomerLookup::resetBuyerIdentity($this);
        $this->customerSearch = '';
        $this->customerQuickEditMode = false;

        if (property_exists($this, 'showStandardBuyerCompletionModal')) {
            $this->showStandardBuyerCompletionModal = false;
            $this->standardBuyerCompletionFieldKeys = [];
            $this->resumeIssueAfterBuyerCompletion = false;
        }

        if (property_exists($this, 'standardBuyerShowMoreAddress')) {
            $this->standardBuyerShowMoreAddress = false;
        }

        $this->resetErrorBag();
        $this->resetValidation();
    }

    public function selectDocumentCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        if (property_exists($this, 'buyerContactOverride')) {
            $this->buyerContactOverride = false;
        }

        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer);
        $this->customerSearch = $customer->name;
        $this->customerQuickEditMode = false;
        $this->toastInfo(__('invoices.customer_selected', ['name' => $customer->name]));

        if (method_exists($this, 'promptStandardBuyerCompletionIfNeeded')) {
            $this->promptStandardBuyerCompletionIfNeeded(resumeIssue: false);
        }
    }

    public function openQuickCustomerModal(): void
    {
        if (! $this->canCreateCustomersFromDocument()) {
            return;
        }

        $this->customerQuickEditMode = false;
        $this->resetQuickCustomerForm();
        $this->showQuickCustomerModal = true;
    }

    public function openEditSelectedCustomerModal(): void
    {
        if ($this->selectedCustomerId === null) {
            return;
        }

        $customer = Customer::query()->find($this->selectedCustomerId);
        if ($customer === null) {
            return;
        }

        $this->populateQuickCustomerFromModel($customer);
        $this->customerQuickEditMode = true;
        $this->showQuickCustomerModal = true;
    }

    public function saveQuickCustomer(LoyaltyService $loyalty): void
    {
        if (! $this->canCreateCustomersFromDocument()) {
            abort(403);
        }

        $this->resetErrorBag();

        if ($this->customerQuickEditMode && $this->selectedCustomerId !== null) {
            $this->saveEditedCustomerProfile($loyalty);

            return;
        }

        $fields = CustomerIdentityRules::quickFields();
        $this->validate(CustomerIdentityRules::forHost($this, $fields, [
            'sa_standard_buyer' => $this->documentStandardBuyerRequired(),
            'relaxed_address' => ! $this->documentStandardBuyerRequired(),
            'phone_unique' => true,
        ]));

        $customer = new Customer([
            'referral_code' => '',
            'points_balance' => 0,
        ]);
        CustomerIdentityPersistence::applyToCustomer(
            $customer,
            CustomerIdentityPersistence::stateFromHost($this, $fields),
        );
        $customer->save();

        $loyalty->ensureReferralCode($customer);

        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer);
        $this->customerSearch = $customer->name;
        $this->showQuickCustomerModal = false;
        $this->customerQuickEditMode = false;
        $this->resetQuickCustomerForm();
        $this->toastSuccess(__('invoices.customer_created_selected'));
    }

    protected function saveEditedCustomerProfile(LoyaltyService $loyalty): void
    {
        $customerId = $this->selectedCustomerId;
        abort_if($customerId === null, 403);

        $customer = Customer::query()->find($customerId);
        abort_if($customer === null, 404);

        $fields = CustomerIdentityRules::quickFields();
        $this->validate(CustomerIdentityRules::forHost($this, $fields, [
            'sa_standard_buyer' => $this->documentStandardBuyerRequired(),
            'relaxed_address' => ! $this->documentStandardBuyerRequired(),
            'phone_unique' => true,
            'phone_unique_ignore_id' => $customer->id,
        ]));

        CustomerIdentityPersistence::applyToCustomer(
            $customer,
            CustomerIdentityPersistence::stateFromHost($this, $fields),
        );
        $customer->save();

        $loyalty->ensureReferralCode($customer);
        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer);
        $this->customerSearch = $customer->name;
        $this->showQuickCustomerModal = false;
        $this->customerQuickEditMode = false;
        $this->resetQuickCustomerForm();
        $this->toastSuccess(__('invoices.customer_profile_updated'));
    }

    protected function populateQuickCustomerFromModel(Customer $customer): void
    {
        CustomerIdentityPersistence::fillHostFromCustomer(
            $this,
            CustomerIdentityRules::quickFields(),
            $customer,
        );

        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];

        $this->customerQuickShowMoreAddress = filled($na['building'] ?? null)
            || filled($na['postal_code'] ?? null)
            || filled($na['district'] ?? null)
            || filled($na['additional'] ?? null);
    }

    protected function resetQuickCustomerForm(): void
    {
        $this->customerQuickName = '';
        $this->customerQuickNameAr = '';
        $this->customerQuickVatRegistered = false;
        $this->customerQuickVat = '';
        $this->customerQuickAltIdType = '';
        $this->customerQuickAltIdNumber = '';
        $this->customerQuickPhone = '';
        $this->customerQuickEmail = '';
        $this->customerQuickCountry = 'SA';
        $this->customerQuickAddrBuilding = '';
        $this->customerQuickAddrStreet = '';
        $this->customerQuickAddrDistrict = '';
        $this->customerQuickAddrCity = 'Riyadh';
        $this->customerQuickAddrPostal = '';
        $this->customerQuickAddrAdditional = '';
        $this->customerQuickShowMoreAddress = false;
        $this->customerQuickEditMode = false;
        $this->resetValidation();
    }

    protected function documentStandardBuyerRequired(): bool
    {
        return false;
    }
}