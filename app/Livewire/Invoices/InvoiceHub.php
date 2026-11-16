<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceDocumentType;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Invoice;
use App\Support\Hub\HubDocumentSigning;
use App\Support\Hub\HubInvoicePrintLinks;
use App\Support\Invoicing\InvoicePayloadTotals;
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
    use ValidatesFieldsLive;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = 'all';

    #[Url]
    public string $signingFilter = 'all';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $sortBy = 'issued_at';

    #[Url]
    public string $sortDir = 'desc';

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

    public function updatingSigningFilter(): void
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

    public function render(): View
    {
        $user = auth('staff')->user();

        return view('livewire.invoices.invoice-hub', [
            'invoices' => $this->invoicePage(),
            'canIssueInvoice' => $user?->can('invoices.issue') ?? false,
        ]);
    }

    private function invoicePage(): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['referencedInvoice'])
            ->where('status', 'issued');

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

        match ($this->signingFilter) {
            'none' => $query->where(function (Builder $q): void {
                $q->whereNull('signed_xml')->orWhere('signed_xml', '');
            }),
            'signed' => $query->whereNotNull('signed_xml')
                ->where('signed_xml', '!=', '')
                ->where(function (Builder $q): void {
                    $q->whereNull('payload->zheart_remote_submitted')
                        ->orWhere('payload->zheart_remote_submitted', false);
                }),
            'submitted' => $query->where(function (Builder $q): void {
                $q->where('payload->zheart_remote_submitted', true)
                    ->orWhereNotNull('payload->zheart_zatca');
            }),
            'zatca_not_sent' => $query->where('payload->signing_submission_status', 'not_submitted'),
            default => null,
        };

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

        $paginator->getCollection()->transform(function (Invoice $invoice): array {
            $totals = InvoicePayloadTotals::forInvoice($invoice);
            $customer = trim((string) data_get($invoice->payload, 'buyer_name', ''));
            $customerAr = trim((string) data_get($invoice->payload, 'buyer_name_ar', ''));
            if ($customer === '' && $customerAr !== '') {
                $customer = $customerAr;
            }

            return [
                'invoice' => $invoice,
                'customer' => $customer !== '' ? $customer : '—',
                'total_inc_vat' => $totals['total_inc_vat'],
                'signing_key' => HubDocumentSigning::signingKey($invoice),
                'print_urls' => HubInvoicePrintLinks::urls($invoice),
                'is_standard' => $invoice->document_type === InvoiceDocumentType::Standard,
            ];
        });

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