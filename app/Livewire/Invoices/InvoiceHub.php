<?php

namespace App\Livewire\Invoices;

use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceZatcaResubmitService;
use App\Support\Hub\HubInvoiceTableRow;
use App\Support\Hub\HubInvoiceZatcaTab;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use App\Business\BusinessContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class InvoiceHub extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use ValidatesFieldsLive;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = 'all';

    #[Url]
    public string $zatcaTab = HubInvoiceZatcaTab::ALL;

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $sortBy = 'issued_at';

    #[Url]
    public string $sortDir = 'desc';

    /** @var array<int, bool> */
    public array $resubmitting = [];

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->business);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingZatcaTab(): void
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

    public function sortByColumn(string $column): void
    {
        $allowed = ['document_number', 'issued_at', 'document_type', 'buyer_name'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'issued_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function resubmitInvoice(int $invoiceId, InvoiceZatcaResubmitService $service): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $this->resubmitting[$invoiceId] = true;

        try {
            $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
            $result = $service->resubmit($invoice);

            if ($result->ok && $result->invoice) {
                $this->toastSuccess(__('invoices.zatca_resubmit_success', ['number' => $result->invoice->document_number]));

                if ($this->zatcaTab === HubInvoiceZatcaTab::FAILED && HubInvoiceZatcaTab::isSuccess($result->invoice)) {
                    $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
                }

                return;
            }

            if ($result->code === 'locked') {
                $this->toastWarning($result->userMessage ?? '');
            } elseif ($result->code === 'already_success') {
                $this->toastInfo($result->userMessage ?? '');
                $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
            } elseif ($result->userMessage) {
                $this->toastError($result->userMessage);
            }
        } finally {
            unset($this->resubmitting[$invoiceId]);
        }
    }

    public function resubmitAllFailed(InvoiceZatcaResubmitService $service): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $query = Invoice::query()->where('status', 'issued');
        HubInvoiceZatcaTab::applyFailedScope($query);
        $invoices = $query->orderBy('id')->limit(40)->get();

        $ok = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $result = $service->resubmit($invoice);
            if ($result->ok) {
                $ok++;
            } else {
                $failed++;
            }
        }

        if ($invoices->isEmpty()) {
            $this->toastInfo(__('invoices.zatca_resubmit_bulk_empty'));

            return;
        }

        $this->toastInfo(__('invoices.zatca_resubmit_bulk_done', ['ok' => $ok, 'fail' => $failed]));

        if ($ok > 0 && $failed === 0) {
            $this->zatcaTab = HubInvoiceZatcaTab::SUCCESS;
        }
    }

    public function render(): View
    {
        $user = auth('staff')->user();
        $canResubmit = ($user?->can('invoices.issue') ?? false)
            && ZatcaSubmissionStatus::canSubmitToZatca($this->business);

        return view('livewire.invoices.invoice-hub', [
            'invoices' => $this->invoicePage(),
            'canIssueInvoice' => $user?->can('invoices.issue') ?? false,
            'canRecordPayment' => $user?->can('invoices.issue') ?? false,
            'canResubmitZatca' => $canResubmit,
            'zatcaFailedCount' => HubInvoiceZatcaTab::countFailed(),
            'zatcaSuccessCount' => HubInvoiceZatcaTab::countSuccess(),
        ]);
    }

    private function invoicePage(): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['referencedInvoice', 'payments'])
            ->whereIn('status', ['issued', 'draft']);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('document_number', 'like', $like)
                    ->orWhere('invoice_hash', 'like', $like)
                    ->orWhere('payload->buyer_name', 'like', $like)
                    ->orWhere('payload->buyer_name_ar', 'like', $like);
            });
        }

        if ($this->typeFilter !== 'all') {
            $query->where('document_type', $this->typeFilter);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('issued_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('issued_at', '<=', $this->dateTo);
        }

        if (in_array($this->zatcaTab, [HubInvoiceZatcaTab::FAILED, HubInvoiceZatcaTab::SUCCESS], true)) {
            HubInvoiceZatcaTab::applyTabScope($query, $this->zatcaTab);
        }

        $dir = $this->sortDir === 'asc' ? 'asc' : 'desc';

        if ($this->sortBy === 'buyer_name') {
            $query->orderBy('payload->buyer_name', $dir);
        } elseif ($this->sortBy === 'document_number') {
            $query->orderBy('document_number', $dir);
        } elseif ($this->sortBy === 'document_type') {
            $query->orderBy('document_type', $dir)->orderByDesc('issued_at');
        } else {
            $query->orderBy('issued_at', $dir);
        }

        $query->orderByDesc('id');

        $paginator = $query->paginate(15);

        $ledger = app(InvoiceDuesLedgerService::class);

        $paginator->getCollection()->transform(
            fn (Invoice $invoice): array => HubInvoiceTableRow::fromInvoice($invoice, $ledger),
        );

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [];
    }
}