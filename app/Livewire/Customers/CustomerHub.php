<?php

namespace App\Livewire\Customers;

use App\Business\BusinessContext;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Customers\CustomerIdentityPersistence;
use App\Support\Customers\CustomerIdentityRules;
use App\Support\Customers\CustomerSchema;
use App\Support\Loyalty\LoyaltyConfig;
use App\Support\Validation\FieldValidationRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class CustomerHub extends Component
{
    use DispatchesToasts;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public ?int $customer = null;

    #[Url(as: 'sort')]
    public string $sort = 'name';

    #[Url(as: 'dir')]
    public string $sortDir = 'asc';

    public bool $showCustomerModal = false;

    public string $customerModalTab = 'details';

    public ?int $editingCustomerId = null;

    public string $customerLegalNameAr = '';

    public string $customerLegalNameEn = '';

    public bool $customerVatRegistered = false;

    public string $customerVat = '';

    public string $customerAltIdType = '';

    public string $customerAltIdNumber = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $customerCountry = 'SA';

    public string $customerAddrBuilding = '';

    public string $customerAddrStreet = '';

    public string $customerAddrDistrict = '';

    public string $customerAddrCity = '';

    public string $customerAddrPostal = '';

    public string $customerAddrAdditional = '';

    public bool $customerShowMoreAddress = false;

    public string $adjustPoints = '';

    public string $adjustNote = '';

    /**
     * @return list<string>
     */
    private function sortableColumns(): array
    {
        $columns = ['name', 'phone', 'email', 'vat_number', 'outstanding'];
        if (CustomerSchema::hasIdentityColumns()) {
            $columns[] = 'cr_number';
            $columns[] = 'city';
        }

        return $columns;
    }

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);

        if (! in_array($this->sort, $this->sortableColumns(), true)) {
            $this->sort = 'name';
        }
        if (! in_array(strtolower($this->sortDir), ['asc', 'desc'], true)) {
            $this->sortDir = 'asc';
        }

        if (request()->query('create') === 'customer') {
            $this->startCustomer();
        }

        if ($this->customer !== null) {
            $this->openCustomerModal($this->customer, 'details');
        }
    }

    public function updatedCustomerVatRegistered(mixed $value): void
    {
        $this->customerVatRegistered = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->customer = null;
        $this->closeCustomerModal();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingSortDir(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortableColumns(), true)) {
            return;
        }

        if ($this->sort === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    public function selectCustomer(int $customerId): void
    {
        $this->openCustomerModal($customerId, 'details');
    }

    public function startEdit(int $customerId): void
    {
        $this->openCustomerModal($customerId, 'details');
    }

    public function openCustomerModal(int $customerId, string $tab = 'details'): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        $this->customer = $customerId;
        $this->hydrateCustomerForm($customer);
        $this->customerModalTab = $tab === 'loyalty' ? 'loyalty' : 'details';
        $this->showCustomerModal = true;
        $this->resetAdjustForm();
    }

    public function setCustomerModalTab(string $tab): void
    {
        if (in_array($tab, ['details', 'loyalty'], true) && $this->editingCustomerId !== null) {
            $this->customerModalTab = $tab;
        }
    }

    public function closeCustomerModal(): void
    {
        $this->showCustomerModal = false;
    }

    public function startCustomer(): void
    {
        $this->customer = null;
        $this->editingCustomerId = null;
        $this->customerModalTab = 'details';
        $this->showCustomerModal = true;
        $this->resetCustomerFormFields();
        $this->resetValidation();
    }

    public function saveCustomer(LoyaltyService $loyalty): void
    {
        $fields = CustomerIdentityRules::hubFields();
        $rules = CustomerIdentityRules::forHost($this, $fields, [
            'phone_unique' => true,
            'phone_unique_ignore_id' => $this->editingCustomerId,
        ]);

        $this->validate(array_merge($this->liveValidationRules(), $rules));

        $state = CustomerIdentityPersistence::stateFromHost($this, $fields);

        if ($this->editingCustomerId !== null) {
            $customer = Customer::query()->findOrFail($this->editingCustomerId);
            CustomerIdentityPersistence::applyToCustomer($customer, $state);
            $customer->save();

            $this->hydrateCustomerForm($customer->fresh());
            $this->toastSuccess(__('customers.updated'));
        } else {
            $customer = new Customer([
                'referral_code' => '',
                'points_balance' => 0,
            ]);
            CustomerIdentityPersistence::applyToCustomer($customer, $state);
            $customer->save();

            $loyalty->ensureReferralCode($customer);
            $this->openCustomerModal($customer->id, 'details');
            $this->toastSuccess(__('customers.created'));
        }
    }

    public function deleteCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        if ($this->customer === $customerId) {
            $this->customer = null;
            $this->closeCustomerModal();
        }

        $customer->delete();
        $this->toastSuccess(__('customers.deleted'));
    }

    public function saveAdjust(LoyaltyService $loyalty): void
    {
        $customer = $this->selectedCustomer();
        if ($customer === null) {
            return;
        }

        $this->validate([
            'adjustPoints' => FieldValidationRules::nonZeroInteger(),
            'adjustNote' => ['required', 'string', 'max:200'],
        ]);

        try {
            $loyalty->adjustPoints($customer, (int) $this->adjustPoints, $this->adjustNote);
        } catch (\InvalidArgumentException $e) {
            $this->addError('adjustPoints', $e->getMessage());

            return;
        }

        $this->resetAdjustForm();
        $this->toastSuccess(__('customers.adjust_saved'));
    }

    public function render(InvoiceDuesLedgerService $ledger): View
    {
        $config = LoyaltyConfig::for($this->business);
        $customers = $this->customerPage($ledger);
        $customerIds = collect($customers->items())->pluck('id')->all();
        $outstandingById = $ledger->totalOutstandingForCustomerIds($customerIds);

        return view('livewire.customers.customer-hub', [
            'customers' => $customers,
            'outstandingById' => $outstandingById,
            'selected' => $this->selectedCustomer(),
            'transactions' => $this->transactionFeed(),
            'loyaltyRates' => [
                'earn' => $config->earnPointsPerSarExVat(),
                'redeem' => $config->redemptionSarExVatPerPoint(),
                'minRedeem' => $config->minimumRedeemPoints(),
                'maxPercent' => $config->maxRedeemPercentOfOrder(),
                'referrerBonus' => $config->referrerBonusPoints(),
            ],
        ]);
    }

    private function customerPage(InvoiceDuesLedgerService $ledger): LengthAwarePaginator
    {
        if ($this->sort === 'outstanding') {
            return $this->customerPageSortedByOutstanding($ledger);
        }

        $query = $this->filteredCustomersQuery();
        $this->applySort($query);

        return $query->paginate(20);
    }

    private function customerPageSortedByOutstanding(InvoiceDuesLedgerService $ledger): LengthAwarePaginator
    {
        $query = $this->filteredCustomersQuery();
        $ids = $query->pluck('id')->all();
        $outstanding = $ledger->totalOutstandingForCustomerIds($ids);

        usort($ids, function (int $a, int $b) use ($outstanding): int {
            $av = $outstanding[$a] ?? 0.0;
            $bv = $outstanding[$b] ?? 0.0;
            $cmp = $av <=> $bv;

            return $this->sortDir === 'desc' ? -$cmp : $cmp;
        });

        $page = max(1, (int) $this->getPage());
        $perPage = 20;
        $total = count($ids);
        $slice = array_slice($ids, ($page - 1) * $perPage, $perPage);
        $order = array_flip($slice);
        $models = $slice === []
            ? collect()
            : Customer::query()->whereIn('id', $slice)->get()->sortBy(fn (Customer $c) => $order[$c->id] ?? 0)->values();

        return new LengthAwarePaginator(
            $models,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    private function filteredCustomersQuery(): Builder
    {
        $query = Customer::query();

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $digits = preg_replace('/\D/', '', $term) ?? '';
            $hasIdentity = CustomerSchema::hasIdentityColumns();
            $query->where(function ($q) use ($like, $term, $digits, $hasIdentity): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('vat_number', 'like', $like)
                    ->orWhere('referral_code', 'like', strtoupper($term).'%');

                if ($hasIdentity) {
                    $q->orWhere('legal_name_ar', 'like', $like)
                        ->orWhere('legal_name_en', 'like', $like)
                        ->orWhere('alt_id_number', 'like', $like)
                        ->orWhere('street_name', 'like', $like)
                        ->orWhere('city', 'like', $like);
                }

                if ($digits !== '') {
                    $q->orWhere('vat_number', 'like', '%'.$digits.'%');
                    if ($hasIdentity) {
                        $q->orWhere('alt_id_number', 'like', '%'.$digits.'%');
                    }
                }

                $q->orWhere('profile->cr_number', 'like', $like)
                    ->orWhere('profile->name_ar', 'like', $like)
                    ->orWhere('profile->national_address->city', 'like', $like);
            });
        }

        return $query;
    }

    private function applySort(Builder $query): void
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $hasIdentity = CustomerSchema::hasIdentityColumns();

        match ($this->sort) {
            'phone' => $query->orderBy('phone', $dir),
            'email' => $query->orderBy('email', $dir),
            'vat_number' => $query->orderBy('vat_number', $dir),
            'cr_number' => $hasIdentity
                ? $query->orderBy('alt_id_number', $dir)->orderBy('profile->cr_number', $dir)
                : $query->orderBy('profile->cr_number', $dir),
            'city' => $hasIdentity
                ? $query->orderBy('city', $dir)->orderBy('profile->national_address->city', $dir)
                : $query->orderBy('profile->national_address->city', $dir),
            default => $query->orderBy('name', $dir)->orderBy('id', $dir),
        };
    }

    private function selectedCustomer(): ?Customer
    {
        if ($this->customer === null) {
            return null;
        }

        return Customer::query()->with('referredBy')->find($this->customer);
    }

    /**
     * @return Collection<int, \App\Models\LoyaltyTransaction>
     */
    private function transactionFeed(): Collection
    {
        $customer = $this->selectedCustomer();
        if ($customer === null) {
            return collect();
        }

        return $customer->loyaltyTransactions()
            ->orderByDesc('occurred_at')
            ->limit(40)
            ->get();
    }

    private function resetAdjustForm(): void
    {
        $this->adjustPoints = '';
        $this->adjustNote = '';
        $this->resetValidation(['adjustPoints', 'adjustNote']);
    }

    private function resetCustomerFormFields(): void
    {
        $this->customerLegalNameAr = '';
        $this->customerLegalNameEn = '';
        $this->customerVatRegistered = false;
        $this->customerVat = '';
        $this->customerAltIdType = '';
        $this->customerAltIdNumber = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerCountry = 'SA';
        $this->customerAddrBuilding = '';
        $this->customerAddrStreet = '';
        $this->customerAddrDistrict = '';
        $this->customerAddrCity = '';
        $this->customerAddrPostal = '';
        $this->customerAddrAdditional = '';
        $this->customerShowMoreAddress = false;
    }

    private function hydrateCustomerForm(Customer $customer): void
    {
        $this->editingCustomerId = $customer->id;
        CustomerIdentityPersistence::fillHostFromCustomer(
            $this,
            CustomerIdentityRules::hubFields(),
            $customer,
        );

        $profile = is_array($customer->profile) ? $customer->profile : [];
        $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];

        $this->customerShowMoreAddress = filled($na['building'] ?? null)
            || filled($na['postal_code'] ?? null)
            || filled($na['district'] ?? null)
            || filled($na['additional'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'adjustPoints' => ['nullable', 'integer', 'not_in:0'],
            'adjustNote' => ['nullable', 'string', 'max:200'],
        ];
    }
}