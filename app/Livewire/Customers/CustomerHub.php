<?php

namespace App\Livewire\Customers;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Support\Validation\FieldValidationRules;
use App\Models\Customer;
use App\Models\BusinessSetting;
use App\Services\Loyalty\LoyaltyService;
use App\Support\Loyalty\LoyaltyConfig;
use App\Business\BusinessContext;
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

    public bool $showCustomerForm = false;

    public string $customerName = '';

    public string $customerPhone = '';

    public string $customerEmail = '';

    public string $adjustPoints = '';

    public string $adjustNote = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->customer = null;
    }

    public function selectCustomer(int $customerId): void
    {
        $this->customer = $customerId;
        $this->resetAdjustForm();
    }

    public function startCustomer(): void
    {
        $this->showCustomerForm = true;
        $this->customerName = '';
        $this->customerPhone = '';
        $this->customerEmail = '';
    }

    public function saveCustomer(LoyaltyService $loyalty): void
    {
        $this->validate(array_merge($this->liveValidationRules(), [
            'customerPhone' => [
                'required',
                'string',
                'max:20',
                'regex:/^(?:\+966|966|0)?5\d{8}$/',
                Rule::unique('customers', 'phone'),
            ],
        ]));

        $customer = Customer::query()->create([
            'name' => $this->customerName,
            'phone' => $this->customerPhone,
            'email' => $this->customerEmail !== '' ? $this->customerEmail : null,
            'referral_code' => '',
            'points_balance' => 0,
        ]);

        $loyalty->ensureReferralCode($customer);

        $this->showCustomerForm = false;
        $this->customer = $customer->id;
        $this->toastSuccess(__('customers.created'));
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

    public function render(): View
    {
        $config = LoyaltyConfig::for($this->tenant);

        return view('livewire.customers.customer-hub', [
            'customers' => $this->customerPage(),
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

    private function customerPage(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Customer::query()->orderByDesc('points_balance')->orderBy('name');

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like, $term): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('referral_code', 'like', strtoupper($term).'%');
            });
        }

        return $query->paginate(12);
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
            'adjustPoints' => ['nullable', 'integer', 'not_in:0'],
            'adjustNote' => ['nullable', 'string', 'max:200'],
        ];
    }
}
