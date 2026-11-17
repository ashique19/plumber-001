<?php

namespace App\Livewire\Payments;

use App\Business\BusinessContext;
use App\Enums\InvoiceArPaymentMethod;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Models\InvoicePayment;
use App\Support\Payments\ReceivedPaymentTableRow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class ReceivedPaymentsHub extends Component
{
    use HasAdminHubPagination;
    use ResolvesBusinessContext;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url]
    public string $customerSearch = '';

    #[Url]
    public string $method = '';

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        $this->authorize('reports.view');

        $tz = config('app.timezone');
        if ($this->dateFrom === '' || $this->dateTo === '') {
            $this->dateTo = now($tz)->toDateString();
            $this->dateFrom = now($tz)->subDays(29)->toDateString();
        }
    }

    public function updatingCustomerSearch(): void
    {
        $this->resetPage();
    }

    public function updatingMethod(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $payments = $this->paymentPage();
        $rows = $payments->getCollection()->map(
            fn (InvoicePayment $payment) => ReceivedPaymentTableRow::fromPayment($payment)
        );
        $payments->setCollection($rows);

        return view('livewire.payments.received-payments-hub', [
            'payments' => $payments,
            'totalAmount' => $this->filteredTotal(),
            'methodOptions' => InvoiceArPaymentMethod::cases(),
        ]);
    }

    private function filteredTotal(): float
    {
        return round((float) $this->filteredQuery()->sum('amount'), 2);
    }

    private function paymentPage(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['invoice', 'customer'])
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->paginate($this->adminPerPage());
    }

    private function filteredQuery(): Builder
    {
        $query = InvoicePayment::query();

        if ($this->dateFrom !== '') {
            $query->whereDate('paid_on', '>=', $this->dateFrom);
        }
        if ($this->dateTo !== '') {
            $query->whereDate('paid_on', '<=', $this->dateTo);
        }
        if ($this->method !== '') {
            $query->where('method', $this->method);
        }
        if (trim($this->customerSearch) !== '') {
            $needle = '%'.trim($this->customerSearch).'%';
            $query->where(function (Builder $q) use ($needle): void {
                $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', $needle)
                    ->orWhere('phone', 'like', $needle)
                    ->orWhere('email', 'like', $needle))
                    ->orWhereHas('invoice', fn (Builder $inv) => $inv
                        ->where('document_number', 'like', $needle)
                        ->orWhere('payload->buyer_name', 'like', $needle)
                        ->orWhere('payload->buyer_name_ar', 'like', $needle));
            });
        }

        return $query;
    }
}