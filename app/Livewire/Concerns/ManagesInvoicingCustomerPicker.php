<?php

namespace App\Livewire\Concerns;

use App\Models\Customer;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Invoicing\InvoicingCustomerLookup;
use App\Support\Validation\FieldValidationRules;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

trait ManagesInvoicingCustomerPicker
{
    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public bool $showQuickCustomerModal = false;

    public bool $customerQuickEditMode = false;

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

    public string $customerQuickAddrPostBox = '';

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

        if ($this->customerQuickEditMode && $this->selectedCustomerId !== null) {
            $this->saveEditedCustomerProfile($loyalty);

            return;
        }

        $rules = [
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
            'customerQuickAddrPostBox' => ['nullable', 'string', 'max:32'],
        ];

        if ($this->documentStandardBuyerRequired()) {
            $rules['customerQuickVat'] = array_merge(FieldValidationRules::vatOptional(), ['required_without:customerQuickCr']);
            $rules['customerQuickCr'] = array_merge(FieldValidationRules::crNumberOptional(), ['required_without:customerQuickVat']);
            $rules['customerQuickAddrBuilding'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrStreet'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrDistrict'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrCity'] = ['required', 'string', 'max:80'];
            $rules['customerQuickAddrPostal'] = FieldValidationRules::postalCodeRequired();
        }

        $this->validate($rules);

        $vatDigits = preg_replace('/\D/', '', $this->customerQuickVat) ?? '';
        $postBox = trim($this->customerQuickAddrPostBox);

        $customer = Customer::query()->create([
            'name' => trim($this->customerQuickName),
            'phone' => $this->customerQuickPhone,
            'email' => $this->customerQuickEmail !== '' ? trim($this->customerQuickEmail) : null,
            'vat_number' => $vatDigits !== '' ? $vatDigits : null,
            'post_box' => $postBox !== '' ? $postBox : null,
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
                    'post_box' => $postBox !== '' ? $postBox : null,
                    'additional' => trim($this->customerQuickAddrAdditional),
                    'country' => strtoupper(trim($this->customerQuickCountry)),
                ],
            ],
        ]);

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

        $rules = [
            'customerQuickName' => ['required', 'string', 'max:160'],
            'customerQuickNameAr' => ['nullable', 'string', 'max:160'],
            'customerQuickPhone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(?:\+966|966|0)?5\d{8}$/',
                Rule::unique('customers', 'phone')->ignore($customer->id),
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
            'customerQuickAddrPostBox' => ['nullable', 'string', 'max:32'],
        ];

        if ($this->documentStandardBuyerRequired()) {
            $rules['customerQuickVat'] = array_merge(FieldValidationRules::vatOptional(), ['required_without:customerQuickCr']);
            $rules['customerQuickCr'] = array_merge(FieldValidationRules::crNumberOptional(), ['required_without:customerQuickVat']);
            $rules['customerQuickAddrBuilding'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrStreet'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrDistrict'] = ['required', 'string', 'max:120'];
            $rules['customerQuickAddrCity'] = ['required', 'string', 'max:80'];
            $rules['customerQuickAddrPostal'] = FieldValidationRules::postalCodeRequired();
        }

        $this->validate($rules);

        $vatDigits = preg_replace('/\D/', '', $this->customerQuickVat) ?? '';
        $postBox = trim($this->customerQuickAddrPostBox);
        $profile = is_array($customer->profile) ? $customer->profile : [];

        $customer->forceFill([
            'name' => trim($this->customerQuickName),
            'phone' => $this->customerQuickPhone,
            'email' => $this->customerQuickEmail !== '' ? trim($this->customerQuickEmail) : null,
            'vat_number' => $vatDigits !== '' ? $vatDigits : null,
            'post_box' => $postBox !== '' ? $postBox : null,
            'profile' => array_merge($profile, [
                'name_ar' => trim($this->customerQuickNameAr),
                'cr_number' => trim($this->customerQuickCr),
                'country' => strtoupper(trim($this->customerQuickCountry)),
                'national_address' => [
                    'building' => trim($this->customerQuickAddrBuilding),
                    'street' => trim($this->customerQuickAddrStreet),
                    'district' => trim($this->customerQuickAddrDistrict),
                    'city' => trim($this->customerQuickAddrCity) !== '' ? trim($this->customerQuickAddrCity) : 'Riyadh',
                    'postal_code' => trim($this->customerQuickAddrPostal),
                    'post_box' => $postBox !== '' ? $postBox : null,
                    'additional' => trim($this->customerQuickAddrAdditional),
                    'country' => strtoupper(trim($this->customerQuickCountry)),
                ],
            ]),
        ])->save();

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
        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = (array) data_get($profile, 'national_address', []);
        $vat = trim((string) ($customer->vat_number ?? ''));

        $this->customerQuickName = $customer->name;
        $this->customerQuickNameAr = (string) data_get($profile, 'name_ar', '');
        $this->customerQuickPhone = (string) ($customer->phone ?? '');
        $this->customerQuickEmail = (string) ($customer->email ?? '');
        $this->customerQuickVat = $vat;
        $this->customerQuickCr = (string) data_get($profile, 'cr_number', '');
        $this->customerQuickCountry = strtoupper((string) data_get($profile, 'country', 'SA')) ?: 'SA';
        $this->customerQuickAddrBuilding = (string) ($na['building'] ?? '');
        $this->customerQuickAddrStreet = (string) ($na['street'] ?? '');
        $this->customerQuickAddrDistrict = (string) ($na['district'] ?? '');
        $this->customerQuickAddrCity = (string) ($na['city'] ?? 'Riyadh');
        $this->customerQuickAddrPostal = (string) ($na['postal_code'] ?? $na['postal'] ?? '');
        $this->customerQuickAddrPostBox = (string) ($customer->post_box ?? $na['post_box'] ?? '');
        $this->customerQuickAddrAdditional = (string) ($na['additional'] ?? '');
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
        $this->customerQuickAddrPostBox = '';
        $this->customerQuickAddrAdditional = '';
        $this->customerQuickEditMode = false;
        $this->resetValidation();
    }

    protected function documentStandardBuyerRequired(): bool
    {
        return false;
    }
}