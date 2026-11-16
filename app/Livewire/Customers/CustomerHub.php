<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Support\Validation\FieldValidationRules;
use App\Models\Customer;
use App\Models\BusinessSetting;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Customers\CustomerProfileFields;
use App\Support\Loyalty\LoyaltyConfig;
use App\Business\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class CustomerHub extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
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

    public bool $showCustomerForm = false;

    public bool $showDetailSheet = false;

    public ?int $editingCustomerId = null;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $customerVat = '';

    public string $customerCr = '';

    public string $customerCity = '';

    public string $adjustPoints = '';

    public string $adjustNote = '';

    private const SORTABLE = [
        'name',
        'phone',
        'email',
        'vat_number',
        'cr_number',
        'city',
        'outstanding',
    ];

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);

        if (! in_array($this->sort, self::SORTABLE, true)) {
            $this->sort = 'name';
        }
        if (! in_array(strtolower($this->sortDir), ['asc', 'desc'], true)) {
            $this->sortDir = 'asc';
        }

        if (request()->query('create') === 'customer') {
            $this->startCustomer();
        }

        if ($this->customer !== null) {
            $this->showDetailSheet = true;
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->customer = null;
        $this->showDetailSheet = false;
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
        if (! in_array($column, self::SORTABLE, true)) {
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
        $this->customer = $customerId;
        $this->resetAdjustForm();
        $this->showDetailSheet = true;
    }

    public function closeDetailSheet(): void
    {
        $this->showDetailSheet = false;
    }

    public function startCustomer(): void
    {
        $this->editingCustomerId = null;
        $this->showCustomerForm = true;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
        $this->customerVat = '';
        $this->customerCr = '';
        $this->customerCity = '';
        $this->resetValidation();
    }

    public function startEdit(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        $this->editingCustomerId = $customer->id;
        $this->showCustomerForm = true;
        $this->customerName = $customer->name;
        $this->customerPhone = (string) ($customer->phone ?? '');
        $this->customerEmail = (string) ($customer->email ?? '');
        $this->customerVat = (string) ($customer->vat_number ?? '');
        $this->customerCr = (string) (CustomerProfileFields::crNumber($customer) ?? '');
        $this->customerCity = (string) (CustomerProfileFields::city($customer) ?? '');
        $this->resetValidation();
    }

    public function saveCustomer(LoyaltyService $loyalty): void
    {
        $phoneRules = [
            'required',
            'string',
            'max:20',
            'regex:/^(?:\+966|966|0)?5\d{8}$/',
        ];
        if ($this->editingCustomerId !== null) {
            $phoneRules[] = Rule::unique('customers', 'phone')->ignore($this->editingCustomerId);
        } else {
            $phoneRules[] = Rule::unique('customers', 'phone');
        }

        $this->validate(array_merge($this->liveValidationRules(), [
            'customerPhone' => $phoneRules,
            'customerVat' => FieldValidationRules::vatOptional(),
            'customerCr' => FieldValidationRules::crNumberOptional(),
            'customerCity' => ['nullable', 'string', 'max:80'],
        ]));

        $vatDigits = preg_replace('/\D/', '', $this->customerVat) ?? '';
        $profilePatch = [
            'cr_number' => trim($this->customerCr) !== '' ? trim($this->customerCr) : null,
            'national_address' => [
                'city' => trim($this->customerCity) !== '' ? trim($this->customerCity) : null,
            ],
        ];

        if ($this->editingCustomerId !== null) {
            $customer = Customer::query()->findOrFail($this->editingCustomerId);
            $profile = is_array($customer->profile) ? $customer->profile : [];
            $na = is_array($profile['national_address'] ?? null) ? $profile['national_address'] : [];

            $customer->forceFill([
                'name' => trim($this->customerName),
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail !== '' ? trim($this->customerEmail) : null,
                'vat_number' => $vatDigits !== '' ? $vatDigits : null,
                'profile' => array_merge($profile, [
                    'cr_number' => $profilePatch['cr_number'],
                    'national_address' => array_merge($na, $profilePatch['national_address']),
                ]),
            ])->save();

            $this->toastSuccess(__('customers.updated'));
        } else {
            $customer = Customer::query()->create([
                'name' => trim($this->customerName),
                'phone' => $this->customerPhone,
                'email' => $this->customerEmail !== '' ? trim($this->customerEmail) : null,
                'vat_number' => $vatDigits !== '' ? $vatDigits : null,
                'referral_code' => '',
                'points_balance' => 0,
                'profile' => [
                    'cr_number' => $profilePatch['cr_number'],
                    'national_address' => $profilePatch['national_address'],
                ],
            ]);

            $loyalty->ensureReferralCode($customer);
            $this->customer = $customer->id;
            $this->toastSuccess(__('customers.created'));
        }

        $this->showCustomerForm = false;
        $this->editingCustomerId = null;
    }

    public function deleteCustomer(int $customerId): void
    {
        $customer = Customer::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        if ($this->customer === $customerId) {
            $this->customer = null;
            $this->showDetailSheet = false;
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
            $query->where(function ($q) use ($like, $term, $digits): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('vat_number', 'like', $like)
                    ->orWhere('referral_code', 'like', strtoupper($term).'%');
                if ($digits !== '') {
                    $q->orWhere('vat_number', 'like', '%'.$digits.'%');
                }
                $q->orWhere('profile->cr_number', 'like', $like)
                    ->orWhere('profile->national_address->city', 'like', $like);
            });
        }

        return $query;
    }

    private function applySort(Builder $query): void
    {
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        match ($this->sort) {
            'phone' => $query->orderBy('phone', $dir),
            'email' => $query->orderBy('email', $dir),
            'vat_number' => $query->orderBy('vat_number', $dir),
            'cr_number' => $query->orderBy('profile->cr_number', $dir),
            'city' => $query->orderBy('profile->national_address->city', $dir),
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

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'customerName' => ['required', 'string', 'max:120'],
            'customerPhone' => FieldValidationRules::saudiMobileRequired(),
            'customerEmail' => FieldValidationRules::emailOptional(),
            'customerVat' => ['nullable', 'string', 'max:32'],
            'customerCr' => ['nullable', 'string', 'max:32'],
            'customerCity' => ['nullable', 'string', 'max:80'],
            'adjustPoints' => ['nullable', 'integer', 'not_in:0'],
            'adjustNote' => ['nullable', 'string', 'max:200'],
        ];
    }
}