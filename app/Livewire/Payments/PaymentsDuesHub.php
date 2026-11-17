<?php

namespace App\Livewire\Payments;

use App\Business\BusinessContext;
use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Hub\HubInvoiceTableRow;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class PaymentsDuesHub extends Component
{
    use DispatchesToasts;
    use HasAdminHubPagination;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    #[Url]
    public string $customerKey = '';

    #[Url]
    public string $invoiceSearch = '';

    #[Url]
    public ?int $selectedInvoiceId = null;

    public string $paidOn = '';

    public string $paymentAmount = '';

    public string $paymentMethod = 'cash';

    public string $paymentReference = '';

    public bool $forRetention = false;

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
        $this->authorize('reports.view');

        if ($this->paidOn === '') {
            $this->paidOn = now('Asia/Riyadh')->toDateString();
        }
    }

    public function selectCustomer(string $key): void
    {
        $this->customerKey = $key;
        $this->dispatch('payments-dues-scroll-statement');
    }

    public function clearCustomer(): void
    {
        $this->customerKey = '';
    }

    public function updatingInvoiceSearch(): void
    {
        $this->resetPage('invoicesPage');
    }

    public function selectInvoice(int $invoiceId): void
    {
        $this->selectedInvoiceId = $invoiceId;
        $this->forRetention = false;
        $this->paymentAmount = '';
        $this->prefillPaymentAmount(app(InvoiceDuesLedgerService::class));
    }

    public function prepareRecordPayment(int $invoiceId): void
    {
        if (! auth('staff')->user()?->can('invoices.issue')) {
            return;
        }

        $this->selectInvoice($invoiceId);
        $this->dispatch('payments-dues-scroll-record');
    }

    public function recordPayment(InvoiceDuesLedgerService $ledger): void
    {
        $this->authorize('invoices.issue');

        $validated = $this->validate([
            'selectedInvoiceId' => ['required', 'integer', 'exists:invoices,id'],
            'paidOn' => ['required', 'date'],
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'in:cash,bank,card'],
            'paymentReference' => ['nullable', 'string', 'max:128'],
            'forRetention' => ['boolean'],
        ]);

        $invoice = Invoice::query()
            ->whereKey($validated['selectedInvoiceId'])
            ->where('status', InvoiceStatus::Issued)
            ->firstOrFail();

        try {
            $ledger->recordPayment(
                $invoice,
                $validated['paidOn'],
                (float) $validated['paymentAmount'],
                InvoiceArPaymentMethod::from($validated['paymentMethod']),
                $validated['paymentReference'] ?? null,
                (bool) $validated['forRetention'],
                auth('staff')->user(),
            );
        } catch (\InvalidArgumentException $e) {
            $this->toastError($e->getMessage());

            return;
        }

        $this->toastSuccess(__('payments.recorded'));
        $this->paymentAmount = '';
        $this->paymentReference = '';
        $this->forRetention = false;
    }

    public function render(InvoiceDuesLedgerService $ledger): View
    {
        $customers = $this->paginateArray($ledger->customerOutstandingSummary(), 'customersPage');
        $statement = null;

        if ($this->customerKey !== '') {
            try {
                $statement = $ledger->customerStatement($this->customerKey);
                $statement['invoices'] = array_map(
                    function (array $row) use ($ledger): array {
                        $hub = HubInvoiceTableRow::fromInvoice($row['invoice'], $ledger);

                        return array_merge($row, [
                            'actions' => $hub['actions'],
                            'more' => $hub['more'],
                            'view_url' => $hub['view_url'],
                            'print_urls' => $hub['print_urls'],
                        ]);
                    },
                    $statement['invoices'],
                );
            } catch (\InvalidArgumentException) {
                $this->customerKey = '';
            }
        }

        if ($this->selectedInvoiceId !== null && $this->paymentAmount === '') {
            $this->prefillPaymentAmount($ledger);
        }

        $invoicePicklist = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->with('payments')
            ->when(trim($this->invoiceSearch) !== '', function ($q): void {
                $like = '%'.trim($this->invoiceSearch).'%';
                $q->where('document_number', 'like', $like);
            })
            ->orderByDesc('issued_at')
            ->paginate($this->adminPerPage(), ['*'], 'invoicesPage')
            ->through(function (Invoice $invoice) use ($ledger): array {
                $balance = $ledger->balanceFor($invoice);

                return [
                    'id' => $invoice->id,
                    'document_number' => $invoice->document_number,
                    'issued_at' => $invoice->issued_at,
                    'status' => $balance['status'],
                    'principal_outstanding' => $balance['principal_outstanding'],
                    'retention_outstanding' => $balance['retention_outstanding'],
                ];
            });

        $selectedBalance = null;
        if ($this->selectedInvoiceId !== null) {
            $invoice = Invoice::query()->with('payments')->find($this->selectedInvoiceId);
            if ($invoice !== null) {
                $selectedBalance = $ledger->balanceFor($invoice);
            }
        }

        $user = auth('staff')->user();

        return view('livewire.payments.payments-dues-hub', [
            'customers' => $customers,
            'statement' => $statement,
            'invoicePicklist' => $invoicePicklist,
            'selectedBalance' => $selectedBalance,
            'totalOutstanding' => $ledger->totalOutstandingPrincipal(),
            'canRecord' => $user?->can('invoices.issue') ?? false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [
            'paidOn' => ['required', 'date'],
            'paymentAmount' => ['required', 'numeric', 'min:0.01'],
            'paymentMethod' => ['required', 'in:cash,bank,card'],
            'paymentReference' => ['nullable', 'string', 'max:128'],
            'forRetention' => ['boolean'],
        ];
    }

    private function prefillPaymentAmount(InvoiceDuesLedgerService $ledger): void
    {
        if ($this->selectedInvoiceId === null) {
            return;
        }

        $invoice = Invoice::query()->with('payments')->find($this->selectedInvoiceId);
        if ($invoice === null) {
            return;
        }

        $balance = $ledger->balanceFor($invoice);
        if ($balance['principal_outstanding'] > 0.005) {
            $this->paymentAmount = $this->formatPaymentPrefill($balance['principal_outstanding']);
            $this->forRetention = false;

            return;
        }

        if ($balance['retention_outstanding'] > 0.005) {
            $this->paymentAmount = $this->formatPaymentPrefill($balance['retention_outstanding']);
            $this->forRetention = true;
        }
    }

    private function formatPaymentPrefill(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
