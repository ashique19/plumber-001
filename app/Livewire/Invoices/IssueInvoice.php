<?php

namespace App\Livewire\Invoices;

use App\Business\BusinessContext;
use App\Data\InvoiceLineData;
use App\Enums\InvoiceDocumentType;
use App\Exceptions\InvoiceSigningException;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ManagesInvoicingCatalogPicker;
use App\Livewire\Concerns\ManagesInvoicingCustomerPicker;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Services\InvoiceIssueService;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Invoicing\IssueInvoicePrefill;
use App\Support\Invoicing\SigningFailureSanitizer;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.pos')]
class IssueInvoice extends Component
{
    use DispatchesToasts;
    use ManagesInvoicingCatalogPicker;
    use ManagesInvoicingCustomerPicker;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;

    public string $mode;

    /**
     * @var list<array{
     *     name: string,
     *     name_ar: string,
     *     quantity: string,
     *     unit_price: string,
     *     vat_rate: string,
     *     product_id: int|null,
     *     unit_label: string
     * }>
     */
    public array $lines = [];

    public string $invoiceDiscountExVat = '0';

    public string $retentionPercent = '';

    public string $retentionFixed = '';

    public string $buyerPhone = '';

    public string $buyerEmail = '';

    public string $buyerName = '';

    public string $buyerVat = '';

    public string $buyerCr = '';

    public string $addrBuilding = '';

    public string $addrStreet = '';

    public string $addrDistrict = '';

    public string $addrCity = 'Riyadh';

    public string $addrPostal = '';

    public string $addrAdditional = '';

    public ?string $buyerNameOptional = '';

    public string $buyerCountry = 'SA';

    public string $dueDate = '';

    public string $supplyDate = '';

    public string $documentNotes = '';

    public function mount(string $mode): void
    {
        $this->resolveBusiness();
        abort_unless(in_array($mode, ['standard', 'simplified'], true), 404);

        $business = $this->business;
        $this->mode = $mode;
        BusinessContext::set($business);

        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $termsDays = max(0, (int) data_get($business->settings, 'invoicing.payment_terms_days', 30));
        $this->dueDate = now('Asia/Riyadh')->addDays($termsDays)->format('Y-m-d');
        $this->supplyDate = now('Asia/Riyadh')->format('Y-m-d');

        IssueInvoicePrefill::applyIfPresent($this, $mode, request());
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    protected function documentStandardBuyerRequired(): bool
    {
        return $this->mode === 'standard';
    }

    public function issue(InvoiceIssueService $issuer): void
    {
        $this->pruneEmptyDocumentLines();
        $this->hydrateCatalogLineNames();
        $this->validate($this->rulesForMode());

        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return;
        }
        StaffBranchContext::remember($branch);
        $type = $this->mode === 'standard' ? InvoiceDocumentType::Standard : InvoiceDocumentType::Simplified;
        $documentNumber = strtoupper($this->mode === 'standard' ? 'STD' : 'SIMP')
            .'-'.now('Asia/Riyadh')->format('Ymd-His');

        $lineItems = array_map(function (array $row): array {
            $item = [
                'name' => trim($row['name']),
                'quantity' => round((float) $row['quantity'], 3),
                'unit_price_ex_vat' => (float) $row['unit_price'],
                'vat_rate' => (float) $row['vat_rate'],
            ];
            $ar = trim((string) ($row['name_ar'] ?? ''));
            if ($ar !== '') {
                $item['name_ar'] = $ar;
            }
            $productId = $row['product_id'] ?? null;
            if ($productId !== null && (int) $productId > 0) {
                $item['product_id'] = (int) $productId;
            }

            return $item;
        }, $this->lines);

        $draft = $issuer->issueDraft($branch, $documentNumber, $type, $lineItems);

        $payload = $draft->payload ?? [];
        if ($type === InvoiceDocumentType::Standard) {
            $vat = preg_replace('/\D/', '', $this->buyerVat) ?? '';
            $payload = array_merge($payload, [
                'buyer_name' => trim($this->buyerName),
                'buyer_vat' => $vat,
                'buyer_cr' => trim($this->buyerCr),
                'buyer_phone' => trim($this->buyerPhone),
                'buyer_email' => trim($this->buyerEmail),
                'buyer_country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
                'buyer_due_date' => trim($this->dueDate),
                'buyer_national_address' => [
                    'building' => $this->addrBuilding,
                    'street' => $this->addrStreet,
                    'district' => $this->addrDistrict,
                    'city' => $this->addrCity,
                    'postal_code' => $this->addrPostal,
                    'additional' => $this->addrAdditional,
                    'country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
                ],
            ]);
        } else {
            if ($this->selectedCustomerId !== null && trim($this->buyerName) !== '') {
                $payload['buyer_name'] = trim($this->buyerName);
            }
            if (trim($this->buyerPhone) !== '') {
                $payload['buyer_phone'] = trim($this->buyerPhone);
            }
            if (trim($this->buyerEmail) !== '') {
                $payload['buyer_email'] = trim($this->buyerEmail);
            }
        }

        $supplyDate = trim($this->supplyDate);
        if ($supplyDate !== '') {
            $payload['supply_date'] = $supplyDate;
        }

        $notes = trim($this->documentNotes);
        if ($notes !== '') {
            $payload['document_notes'] = $notes;
        }

        $discount = max(0, (float) $this->invoiceDiscountExVat);
        if ($discount > 0) {
            $payload['invoice_discount_ex_vat'] = round($discount, 2);
        }

        $retentionFixed = max(0, (float) $this->retentionFixed);
        $retentionPercent = max(0, (float) $this->retentionPercent);
        if ($retentionFixed > 0) {
            $payload['retention_fixed'] = round($retentionFixed, 2);
        } elseif ($retentionPercent > 0) {
            $payload['retention_percent'] = round($retentionPercent, 2);
        }

        $draft->forceFill(['payload' => $payload])->save();

        try {
            $outcome = $issuer->issue($draft->fresh());
        } catch (InvoiceSigningException $e) {
            Log::warning('invoice.livewire_issue_signing_failed', SigningFailureSanitizer::logContext($e));
            $this->toastError($this->signingToastMessage($e));

            return;
        } catch (\Throwable $e) {
            Log::error('invoice.livewire_issue_failed', SigningFailureSanitizer::logContext($e));
            $this->toastError(__('invoices.errors.issue_failed'));

            return;
        }

        $issued = $outcome->invoice;

        if (! $outcome->signingSucceeded) {
            $this->toastWarning(
                __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]),
                persistent: true,
                dedupe: false,
            );
        }

        $this->toastSuccess(__('invoices.issue_success', ['number' => $issued->document_number]));
        $this->redirect(route('admin.invoices'), navigate: true);
    }

    private function signingToastMessage(InvoiceSigningException $exception): string
    {
        $message = mb_strtolower($exception->getMessage());
        if (str_contains($message, 'credential') || str_contains($message, 'encrypted')) {
            return __('invoices.errors.signing_credentials_invalid');
        }

        return SigningFailureSanitizer::userMessage(__('invoices.errors.signing_failed'), $exception);
    }

    public function render(): View
    {
        $isStandard = $this->mode === 'standard';

        return view('livewire.invoices.issue-invoice', [
            'pageTitle' => $isStandard
                ? __('invoices.issue_standard_title')
                : __('invoices.issue_simplified_title'),
            'isStandard' => $isStandard,
            'customerSearchResults' => $this->customerSearchResults(),
            'addLineProductSearchResults' => $this->addLineProductSearchResults(),
            'canCreateCustomers' => $this->canCreateCustomersFromDocument(),
            'canCreateProducts' => $this->canCreateProductsFromDocument(),
            'productCategories' => ProductCategory::query()->orderBy('name_en')->get(),
            'previewTotals' => $this->previewTotals(),
            'zatcaNotReady' => ! ZatcaSubmissionStatus::canSubmitToZatca($this->business),
        ]);
    }

    /**
     * @return array<string, float>
     */
    public function previewTotals(): array
    {
        $lines = [];
        foreach ($this->lines as $row) {
            if (! filled($row['product_id'] ?? null)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $unitPrice = (float) ($row['unit_price'] ?? 0);
            if ($name === '' && $unitPrice <= 0) {
                continue;
            }
            $lines[] = new InvoiceLineData(
                name: $name !== '' ? $name : __('invoices.line_name'),
                quantity: (float) ($row['quantity'] ?? 1),
                unitPriceExVat: $unitPrice,
                vatRate: (float) ($row['vat_rate'] ?? 0),
                nameAr: filled($row['name_ar'] ?? null) ? (string) $row['name_ar'] : null,
            );
        }

        $retentionPercent = trim($this->retentionPercent) !== '' ? (float) $this->retentionPercent : null;
        $retentionFixed = trim($this->retentionFixed) !== '' ? (float) $this->retentionFixed : null;

        return InvoiceDocumentTotals::compute(
            $lines,
            (float) $this->invoiceDiscountExVat,
            $retentionPercent,
            $retentionFixed,
        );
    }

    /**
     * @return array{
     *     name: string,
     *     name_ar: string,
     *     quantity: string,
     *     unit_price: string,
     *     vat_rate: string,
     *     product_id: int|null,
     *     unit_label: string
     * }
     */
    private function hydrateCatalogLineNames(): void
    {
        $productIds = [];
        foreach ($this->lines as $row) {
            $productId = $row['product_id'] ?? null;
            if ($productId && trim((string) ($row['name'] ?? '')) === '') {
                $productIds[(int) $productId] = true;
            }
        }

        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', array_keys($productIds))
            ->get()
            ->keyBy('id');

        foreach ($this->lines as $index => $row) {
            $productId = $row['product_id'] ?? null;
            if (! $productId || trim((string) ($row['name'] ?? '')) !== '') {
                continue;
            }

            $product = $products->get((int) $productId);
            if ($product === null) {
                continue;
            }

            $nameEn = trim((string) ($product->name_en ?? ''));
            if ($nameEn === '') {
                $nameEn = $product->displayName('en');
            }

            $this->lines[$index]['name'] = $nameEn;

            if (trim((string) ($this->lines[$index]['name_ar'] ?? '')) === '') {
                $this->lines[$index]['name_ar'] = trim((string) ($product->name_ar ?? ''));
            }

            if (trim((string) ($this->lines[$index]['unit_price'] ?? '')) === '') {
                $this->lines[$index]['unit_price'] = (string) $product->price_ex_vat;
            }

            if (trim((string) ($this->lines[$index]['vat_rate'] ?? '')) === '') {
                $this->lines[$index]['vat_rate'] = (string) $product->vat_rate;
            }
        }
    }

    /**
     * @return array{
     *     name: string,
     *     name_ar: string,
     *     quantity: string,
     *     unit_price: string,
     *     vat_rate: string,
     *     product_id: int|null,
     *     unit_label: string
     * }
     */
    protected function emptyLine(): array
    {
        return [
            'name' => '',
            'name_ar' => '',
            'quantity' => '1',
            'unit_price' => '',
            'vat_rate' => '15',
            'product_id' => null,
            'unit_label' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForMode(): array
    {
        $lineRules = [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.name' => ['required', 'string', 'max:200'],
            'lines.*.name_ar' => ['nullable', 'string', 'max:200'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'invoiceDiscountExVat' => ['nullable', 'numeric', 'min:0'],
            'retentionPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'retentionFixed' => ['nullable', 'numeric', 'min:0'],
            'buyerPhone' => ['nullable', 'string', 'max:32'],
            'buyerEmail' => ['nullable', 'email', 'max:120'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'supplyDate' => ['nullable', 'date'],
            'documentNotes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->mode === 'simplified') {
            return $lineRules;
        }

        return array_merge($lineRules, [
            'selectedCustomerId' => ['required', 'integer', 'exists:customers,id'],
            'buyerName' => ['required', 'string', 'max:200'],
            'buyerVat' => array_merge(FieldValidationRules::vatOptional(), ['required_without:buyerCr']),
            'buyerCr' => array_merge(FieldValidationRules::crNumberOptional(), ['required_without:buyerVat']),
            'dueDate' => ['required', 'date'],
            'buyerCountry' => ['required', 'string', 'size:2'],
            'addrBuilding' => ['required', 'string', 'max:32'],
            'addrStreet' => ['required', 'string', 'max:120'],
            'addrDistrict' => ['nullable', 'string', 'max:120'],
            'addrCity' => ['required', 'string', 'max:80'],
            'addrPostal' => FieldValidationRules::postalCodeRequired(),
            'addrAdditional' => ['nullable', 'string', 'max:32'],
        ]);
    }

    private function pruneEmptyDocumentLines(): void
    {
        $this->lines = array_values(array_filter(
            $this->lines,
            fn (array $row): bool => filled($row['product_id'] ?? null),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function liveValidationRules(): array
    {
        return $this->rulesForMode();
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'buyerVat.required_without' => __('invoices.errors.buyer_id_required'),
            'buyerCr.required_without' => __('invoices.errors.buyer_id_required'),
            'selectedCustomerId.required' => __('invoices.errors.customer_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'buyerName' => __('invoices.buyer_name'),
            'buyerNameOptional' => __('invoices.buyer_optional'),
            'buyerVat' => __('business.fields.vat_number'),
            'buyerCr' => __('business.fields.cr_number'),
            'addrBuilding' => __('business.fields.building'),
            'addrStreet' => __('business.fields.street'),
            'addrDistrict' => __('business.fields.district'),
            'addrCity' => __('business.fields.city'),
            'addrPostal' => __('business.fields.postal'),
            'addrAdditional' => __('business.fields.additional'),
            'dueDate' => __('invoices.due_date'),
            'buyerCountry' => __('invoices.buyer_country'),
            'lines.*.name' => __('invoices.line_name'),
            'lines.*.quantity' => __('invoices.line_qty'),
            'lines.*.unit_price' => __('invoices.line_price_ex_vat'),
            'lines.*.vat_rate' => __('invoices.line_vat'),
        ];
    }
}
