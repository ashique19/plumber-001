<?php

namespace App\Livewire\Payments;

use App\Business\BusinessContext;
use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class PaymentsDuesHub extends Component
{
    use DispatchesToasts;
    use ResolvesBusinessContext;

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
    }

    public function clearCustomer(): void
    {
        $this->customerKey = '';
    }

    public function selectInvoice(int $invoiceId): void
    {
        $this->selectedInvoiceId = $invoiceId;
        $this->forRetention = false;
        $this->paymentAmount = '';
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
        $customers = $ledger->customerOutstandingSummary();
        $statement = null;

        if ($this->customerKey !== '') {
            try {
                $statement = $ledger->customerStatement($this->customerKey);
            } catch (\InvalidArgumentException) {
                $this->customerKey = '';
            }
        }

        $invoicePicklist = Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->with('payments')
            ->when(trim($this->invoiceSearch) !== '', function ($q): void {
                $like = '%'.trim($this->invoiceSearch).'%';
                $q->where('document_number', 'like', $like);
            })
            ->orderByDesc('issued_at')
            ->limit(25)
            ->get()
            ->map(function (Invoice $invoice) use ($ledger): array {
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
}
