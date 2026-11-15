<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Invoicing\InvoicingCustomerLookup;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

trait ManagesInvoicingCustomerPicker
{
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showQuickCustomerModal = false;

    public string $customerQuickName = '';

    public string $customerQuickNameAr = '';

    public string $customerQuickPhone = '';

    public string $customerQuickEmail = '';

    public string $customerQuickVat = '';

    public string $customerQuickCr = '';

    public string $customerQuickCountry = 'SA';

    public string $customerQuickAddrBuilding = '';

    public string $customerQuickAddrStreet = '';

    public string $customerQuickAddrDistrict = '';

    public string $customerQuickAddrCity = 'Riyadh';

    public string $customerQuickAddrPostal = '';

    public string $customerQuickAddrAdditional = '';

    public function customerSearchResults(): Collection
    {
        return InvoicingCustomerLookup::search($this->customerSearch);
    }

    public function canCreateCustomersFromDocument(): bool
    {
        return auth('staff')->user()?->can('reports.view') ?? false;
    }

    public function selectDocumentCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer);
        $this->customerSearch = $customer->name;
        $this->toastInfo(__('invoices.customer_selected', ['name' => $customer->name]));
    }

    public function openQuickCustomerModal(): void
    {
        if (! $this->canCreateCustomersFromDocument()) {
            return;
        }

        $this->showQuickCustomerModal = true;
    }

    public function saveQuickCustomer(LoyaltyService $loyalty): void
    {
        if (! $this->canCreateCustomersFromDocument()) {
            abort(403);
        }

        $this->validate([
            'customerQuickName' => ['required', 'string', 'max:160'],
            'customerQuickNameAr' => ['nullable', 'string', 'max:160'],
            'customerQuickPhone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(?:\+966|966|0)?5\d{8}$/',
                Rule::unique('customers', 'phone'),
            ],
            'customerQuickEmail' => ['nullable', 'email', 'max:160'],
            'customerQuickVat' => ['nullable', 'string', 'max:32'],
            'customerQuickCr' => ['nullable', 'string', 'max:32'],
            'customerQuickCountry' => ['required', 'string', 'size:2'],
            'customerQuickAddrBuilding' => ['nullable', 'string', 'max:120'],
            'customerQuickAddrStreet' => ['nullable', 'string', 'max:120'],
            'customerQuickAddrDistrict' => ['nullable', 'string', 'max:120'],
            'customerQuickAddrCity' => ['nullable', 'string', 'max:80'],
            'customerQuickAddrPostal' => ['nullable', 'string', 'max:20'],
            'customerQuickAddrAdditional' => ['nullable', 'string', 'max:160'],
        ]);

        $vatDigits = preg_replace('/\D/', '', $this->customerQuickVat) ?? '';

        $customer = Customer::query()->create([
            'name' => trim($this->customerQuickName),
            'phone' => $this->customerQuickPhone,
            'email' => $this->customerQuickEmail !== '' ? trim($this->customerQuickEmail) : null,
            'vat_number' => $vatDigits !== '' ? $vatDigits : null,
            'referral_code' => '',
            'points_balance' => 0,
            'profile' => [
                'name_ar' => trim($this->customerQuickNameAr),
                'cr_number' => trim($this->customerQuickCr),
                'country' => strtoupper(trim($this->customerQuickCountry)),
                'national_address' => [
                    'building' => trim($this->customerQuickAddrBuilding),
                    'street' => trim($this->customerQuickAddrStreet),
                    'district' => trim($this->customerQuickAddrDistrict),
                    'city' => trim($this->customerQuickAddrCity) !== '' ? trim($this->customerQuickAddrCity) : 'Riyadh',
                    'postal_code' => trim($this->customerQuickAddrPostal),
                    'additional' => trim($this->customerQuickAddrAdditional),
                    'country' => strtoupper(trim($this->customerQuickCountry)),
                ],
            ],
        ]);

        $loyalty->ensureReferralCode($customer);

        InvoicingCustomerLookup::applyCustomerToBuyer($this, $customer);
        $this->customerSearch = $customer->name;
        $this->showQuickCustomerModal = false;
        $this->resetQuickCustomerForm();
        $this->toastSuccess(__('invoices.customer_created_selected'));
    }

    protected function resetQuickCustomerForm(): void
    {
        $this->customerQuickName = '';
        $this->customerQuickNameAr = '';
        $this->customerQuickPhone = '';
        $this->customerQuickEmail = '';
        $this->customerQuickVat = '';
        $this->customerQuickCr = '';
        $this->customerQuickCountry = 'SA';
        $this->customerQuickAddrBuilding = '';
        $this->customerQuickAddrStreet = '';
        $this->customerQuickAddrDistrict = '';
        $this->customerQuickAddrCity = 'Riyadh';
        $this->customerQuickAddrPostal = '';
        $this->customerQuickAddrAdditional = '';
        $this->resetValidation();
    }
}