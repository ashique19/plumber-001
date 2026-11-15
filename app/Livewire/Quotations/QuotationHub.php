<?php

namespace App\Livewire\Quotations;

use App\Enums\InvoiceDocumentType;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\BusinessSetting;
use App\Models\Quotation;
use App\Support\Invoicing\IssueInvoicePrefill;
use App\Business\BusinessContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class QuotationHub extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use ValidatesFieldsLive;
    use WithPagination;

    #[Url]
    public string $search = '';

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
        $query = Quotation::query()
            ->with(['branch'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('quotation_number', 'like', $like);
            });
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
}