<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceDocumentType;
use App\Models\Invoice;
use App\Models\PosOrder;
use App\Models\BusinessSetting;
use App\Business\BusinessContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use App\Livewire\Concerns\ValidatesFieldsLive;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
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

    public function mount(): void
    {
        $this->resolveBusiness();
        BusinessContext::set($this->tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
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

    public function render(): View
    {
        return view('livewire.invoices.invoice-hub', [
            'invoices' => $this->invoicePage(),
        ]);
    }

    private function invoicePage(): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with(['referencedInvoice'])
            ->where('status', 'issued')
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('document_number', 'like', $like)
                    ->orWhere('invoice_hash', 'like', $like);
            });
        }

        if ($this->typeFilter !== 'all') {
            $query->where('document_type', $this->typeFilter);
        }

        return $query->paginate(15);
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return [];
    }

    public function orderFor(Invoice $invoice): ?PosOrder
    {
        if ($invoice->source_type !== PosOrder::class || $invoice->source_id === null) {
            return null;
        }

        return PosOrder::query()->find($invoice->source_id);
    }
}
