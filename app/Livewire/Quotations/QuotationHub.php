<?php

namespace App\Livewire\Quotations;

use App\Business\BusinessContext;
use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\HasAdminHubPagination;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Support\Hub\HubChronologicalSort;
use App\Support\Hub\HubQuotationPrintLinks;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Invoicing\IssueInvoicePrefill;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class QuotationHub extends Component
{
    use DispatchesToasts;
    use HasAdminHubPagination;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    #[Url]
    public string $search = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $sortBy = 'created_at';

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
        $allowed = ['quotation_number', 'created_at', 'validity_until', 'buyer_name'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = $column === 'created_at' ? 'desc' : 'asc';
        }

        $this->resetPage();
    }

    public function convertToInvoice(int $quotationId): void
    {
        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $quotation = Quotation::query()->findOrFail($quotationId);

        $mode = BusinessSetting::singleton()->defaultInvoiceDocumentType() === InvoiceDocumentType::Standard
            ? 'standard'
            : 'simplified';

        $prefill = IssueInvoicePrefill::fromQuotation($quotation);
        $prefill['mode'] = $mode;
        session([IssueInvoicePrefill::SESSION_KEY => $prefill]);

        $this->redirect(
            route('admin.invoices.issue', ['mode' => $mode, 'from_quotation' => $quotation->id]),
            navigate: true,
        );
    }

    public function render(): View
    {
        $user = auth('staff')->user();

        return view('livewire.quotations.quotation-hub', [
            'quotations' => $this->quotationPage(),
            'canManageQuotations' => $user?->can('invoices.issue') ?? false,
        ]);
    }

    private function quotationPage(): LengthAwarePaginator
    {
        $query = Quotation::query()->with(['branch']);

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('quotation_number', 'like', $like)
                    ->orWhere('payload->buyer_name', 'like', $like);
            });
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        $descending = $this->sortDir !== 'asc';
        $dir = $descending ? 'desc' : 'asc';

        match ($this->sortBy) {
            'quotation_number' => $query->orderBy('quotation_number', $dir),
            'validity_until' => $query->orderBy('validity_until', $dir),
            'buyer_name' => $query->orderBy('payload->buyer_name', $dir),
            default => HubChronologicalSort::applyLatestFirst($query, 'created_at', $descending),
        };

        if ($this->sortBy !== 'created_at') {
            HubChronologicalSort::applyTieBreakers($query, $descending);
        }

        $paginator = $query->paginate($this->adminPerPage());

        $paginator->getCollection()->transform(function (Quotation $quotation): array {
            $customer = trim((string) data_get($quotation->payload, 'buyer_name', '')) ?: '—';
            $lines = [];
            foreach ((array) ($quotation->payload['lines'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lines[] = new InvoiceLineData(
                    name: (string) ($row['name'] ?? ''),
                    quantity: (float) ($row['quantity'] ?? 1),
                    unitPriceExVat: (float) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? 0),
                    vatRate: (float) ($row['vat_rate'] ?? 15),
                    discountExVat: (float) ($row['discount_ex_vat'] ?? 0),
                );
            }

            $totals = InvoiceDocumentTotals::compute(
                $lines,
                (float) ($quotation->payload['invoice_discount_ex_vat'] ?? 0),
                isset($quotation->payload['retention_percent']) ? (float) $quotation->payload['retention_percent'] : null,
                isset($quotation->payload['retention_fixed']) ? (float) $quotation->payload['retention_fixed'] : null,
            );

            return [
                'quotation' => $quotation,
                'customer' => $customer,
                'total_inc_vat' => $totals['total_inc_vat'],
                'print_urls' => HubQuotationPrintLinks::urls($quotation),
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
