<?php

namespace App\Livewire\Invoices;

use App\Business\BusinessContext;
use App\Data\InvoiceLineData;
use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Exceptions\InvoiceSigningException;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ManagesDocumentBuyerContact;
use App\Livewire\Concerns\ManagesDocumentCreateChrome;
use App\Livewire\Concerns\ManagesInvoicingCatalogPicker;
use App\Livewire\Concerns\ManagesInvoicingCustomerPicker;
use App\Livewire\Concerns\ManagesInvoicePaymentTerms;
use App\Livewire\Concerns\ManagesStandardBuyerCompletion;
use App\Support\Invoicing\DocumentPaymentTerms;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\Invoice;
use App\Support\Catalog\QuickProductCategoryOptions;
use App\Models\Product;
use App\Services\InvoiceIssueService;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Customers\CustomerIdentityRules;
use App\Support\Invoicing\StandardBuyerFieldGaps;
use App\Support\Invoicing\DocumentCreateUiSupport;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\Invoicing\IssueInvoicePrefill;
use App\Support\Invoicing\SigningFailureSanitizer;
use App\Support\Hub\HubFormSubmissionIdempotency;
use App\Support\Invoicing\ZatcaSubmissionStatus;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use App\Support\Validation\QuickModalValidationAttributes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.pos')]
class IssueInvoice extends Component
{
    use DispatchesToasts;
    use ManagesDocumentBuyerContact;
    use ManagesDocumentCreateChrome;
    use ManagesInvoicingCatalogPicker;
    use ManagesInvoicingCustomerPicker;
    use ManagesInvoicePaymentTerms;
    use ManagesStandardBuyerCompletion;
    use ResolvesBusinessContext;
    use ValidatesFieldsLive;
    use WithFileUploads;

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

    public string $buyerNameAr = '';

    public bool $buyerVatRegistered = false;

    public string $buyerVat = '';

    public string $buyerAltIdType = '';

    public string $buyerAltIdNumber = '';

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

    public bool $standardBuyerShowMoreAddress = false;

    public string $paymentTerms = 'cash';

    public string $paymentMethod = 'cash';

    public string $amountReceived = '';

    #[Locked]
    public ?int $draftInvoiceId = null;

    #[Locked]
    public string $issueSubmissionToken = '';

    public bool $simplifiedCustomerExpanded = false;

    public function expandSimplifiedCustomerSection(): void
    {
        $this->simplifiedCustomerExpanded = true;
    }

    public function updatedBuyerVatRegistered(mixed $value): void
    {
        $this->buyerVatRegistered = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function updatedMode(string $value): void
    {
        if (! in_array($value, ['standard', 'simplified'], true)) {
            $this->mode = 'simplified';

            return;
        }

        if ($value === 'standard' && $this->supplyDate === '') {
            $this->supplyDate = now('Asia/Riyadh')->format('Y-m-d');
        }

        if ($value === 'standard') {
            $this->bootstrapDocumentPaymentTerms();
            $this->simplifiedCustomerExpanded = true;
        } elseif ($this->selectedCustomerId === null) {
            $this->simplifiedCustomerExpanded = false;
        }
    }

    public function mount(string $mode): void
    {
        $this->resolveBusiness();
        abort_unless(in_array($mode, ['standard', 'simplified'], true), 404);

        $business = $this->business;
        $this->mode = $mode;
        BusinessContext::set($business);

        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

        $this->supplyDate = now('Asia/Riyadh')->format('Y-m-d');

        IssueInvoicePrefill::applyIfPresent($this, $mode, request());

        $addProduct = request()->query('add_product');
        if (is_numeric($addProduct)) {
            $this->addLineFromProduct((int) $addProduct);
        }

        $this->paymentMethod = InvoiceArPaymentMethod::Cash->value;
        $this->bootstrapDocumentPaymentTerms();
        $this->issueSubmissionToken = (string) Str::uuid();

        DocumentCreateUiSupport::assignMissingLineIds($this->lines);
        $this->applyBuyerContactOverrideFromState($this->selectedCustomerId);

        if ($this->mode === 'simplified' && $this->selectedCustomerId !== null) {
            $this->simplifiedCustomerExpanded = true;
        }
    }

    public function hydrate(): void
    {
        DocumentCreateUiSupport::assignMissingLineIds($this->lines);
    }

    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['paymentTerms', 'mode', 'supplyDate', 'dueDate', 'amountReceived'], true)) {
            return;
        }

        if (
            str_starts_with((string) $propertyName, 'lines')
            || in_array($propertyName, ['invoiceDiscountExVat', 'retentionPercent', 'retentionFixed'], true)
        ) {
            $this->syncPaymentAmountsAfterTotalsChange();
        }
    }

    public function saveDraft(InvoiceIssueService $issuer): void
    {
        $invoice = $this->persistDraftInvoice($issuer);
        if ($invoice === null) {
            return;
        }

        $this->toastSuccess(__('invoices.draft_saved', ['number' => $invoice->document_number]));
    }

    public function previewDocument(InvoiceIssueService $issuer): void
    {
        $invoice = $this->persistDraftInvoice($issuer);
        if ($invoice === null) {
            return;
        }

        $layout = $invoice->document_type === InvoiceDocumentType::Standard ? 'a4' : 'cash';
        $url = route('admin.invoices.print', [
            'invoice' => $invoice,
            'layout' => $layout,
            'preview' => 1,
        ]);

        $this->dispatch('document-open-preview', url: $url);
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
        return $this->resolvedIssueMode() === 'standard';
    }

    protected function resolvedIssueMode(): string
    {
        if (in_array($this->mode, ['standard', 'simplified'], true)) {
            return $this->mode;
        }

        $routeMode = request()->route('mode');
        if (is_string($routeMode) && in_array($routeMode, ['standard', 'simplified'], true)) {
            $this->mode = $routeMode;

            return $routeMode;
        }

        throw new \RuntimeException('Invoice issue mode is missing or invalid.');
    }

    public function issue(InvoiceIssueService $issuer): void
    {
        $this->pruneEmptyDocumentLines();
        $this->hydrateCatalogLineNames();

        if ($this->lines === []) {
            $this->addError('document', __('invoices.errors.lines_required'));
            $this->dispatch('document-validation-failed');

            return;
        }

        if (! $this->promptStandardBuyerCompletionIfNeeded(resumeIssue: true)) {
            return;
        }

        try {
            $this->validate($this->rulesForMode());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->dispatch('document-validation-failed');

            throw $exception;
        }

        $actorId = auth('staff')->id();
        $existingIssuedId = HubFormSubmissionIdempotency::storedResourceId('invoice_issue', $this->issueSubmissionToken, $actorId);
        if ($existingIssuedId !== null) {
            $this->redirect(route('admin.invoices'), navigate: true);

            return;
        }

        $lock = HubFormSubmissionIdempotency::acquireLock('invoice_issue', $this->issueSubmissionToken, $actorId);
        if ($lock === null) {
            $replayId = HubFormSubmissionIdempotency::waitForStoredResourceId('invoice_issue', $this->issueSubmissionToken, $actorId);
            if ($replayId !== null) {
                $this->redirect(route('admin.invoices'), navigate: true);
            }

            return;
        }

        try {
            $replayId = HubFormSubmissionIdempotency::storedResourceId('invoice_issue', $this->issueSubmissionToken, $actorId);
            if ($replayId !== null) {
                $this->redirect(route('admin.invoices'), navigate: true);

                return;
            }

            $draft = $this->persistDraftInvoice($issuer, forIssue: true);
            if ($draft === null) {
                return;
            }

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

            $this->recordInitialPaymentIfNeeded($issued);

            HubFormSubmissionIdempotency::rememberResourceId(
                'invoice_issue',
                $this->issueSubmissionToken,
                $actorId,
                (int) $issued->id,
            );

            if (! $outcome->signingSucceeded) {
                $zatcaMessage = filled($outcome->signingFailureMessage)
                    ? $outcome->signingFailureMessage
                    : __('invoices.issue_saved_not_sent_zatca', ['number' => $issued->document_number]);
                $this->toastWarning($zatcaMessage, persistent: true, dedupe: false);
            }

            $this->toastSuccess(__('invoices.issue_success', ['number' => $issued->document_number]));
            $this->redirect(route('admin.invoices'), navigate: true);
        } finally {
            $lock->release();
        }
    }

    private function persistDraftInvoice(InvoiceIssueService $issuer, bool $forIssue = false): ?Invoice
    {
        $this->pruneEmptyDocumentLines();
        $this->hydrateCatalogLineNames();

        if ($this->lines === []) {
            $this->addError('document', __('invoices.errors.lines_required'));
            $this->dispatch('document-validation-failed');

            return null;
        }

        if (! $forIssue && $this->mode === 'standard' && ! $this->promptStandardBuyerCompletionIfNeeded(resumeIssue: false)) {
            return null;
        }

        if (! $forIssue) {
            try {
                $this->validate($this->rulesForMode(relaxedDueDate: true));
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->dispatch('document-validation-failed');

                throw $exception;
            }
        }

        $branch = StaffBranchContext::resolve();
        if ($branch === null) {
            $this->toastError(__('pos.empty_branches'));

            return null;
        }
        StaffBranchContext::remember($branch);

        $issueMode = $this->resolvedIssueMode();
        $type = $issueMode === 'standard' ? InvoiceDocumentType::Standard : InvoiceDocumentType::Simplified;

        $existing = $this->draftInvoiceId !== null
            ? Invoice::query()->whereKey($this->draftInvoiceId)->where('status', InvoiceStatus::Draft)->first()
            : null;

        $documentNumber = $existing?->document_number
            ?? strtoupper(($issueMode === 'standard' ? 'STD' : 'SIMP').'-'.now('Asia/Riyadh')->format('Ymd-His'));

        $draft = $existing ?? $issuer->issueDraft($branch, $documentNumber, $type, $this->buildLineItems());

        if ($existing !== null && $existing->document_type !== $type) {
            $draft->forceFill(['document_type' => $type])->save();
        }

        $payload = $this->buildIssuePayload($type);
        $draft->forceFill(['payload' => $payload, 'branch_id' => $branch->id])->save();
        $this->draftInvoiceId = $draft->id;

        return $draft->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLineItems(): array
    {
        return array_map(function (array $row): array {
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
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIssuePayload(InvoiceDocumentType $type): array
    {
        $payload = ['lines' => $this->buildLineItems()];

        if ($type === InvoiceDocumentType::Standard) {
            $vatRegistered = $this->buyerVatRegistered;
            $vat = $vatRegistered ? (preg_replace('/\D/', '', $this->buyerVat) ?? '') : '';
            $altType = ! $vatRegistered ? strtoupper(trim($this->buyerAltIdType)) : '';
            $altNumber = ! $vatRegistered ? trim($this->buyerAltIdNumber) : '';
            $buyerCr = $altType === 'CRN' && $altNumber !== ''
                ? $altNumber
                : trim($this->buyerCr);

            $payload = array_merge($payload, [
                'buyer_customer_id' => $this->selectedCustomerId,
                'buyer_name' => trim($this->buyerName),
                'buyer_name_ar' => trim($this->buyerNameAr) !== '' ? trim($this->buyerNameAr) : null,
                'buyer_name_en' => trim($this->buyerName) !== '' ? trim($this->buyerName) : null,
                'buyer_vat_registered' => $vatRegistered,
                'buyer_vat' => $vat !== '' ? $vat : null,
                'buyer_alt_id_type' => $altType !== '' ? $altType : null,
                'buyer_alt_id_number' => $altNumber !== '' ? $altNumber : null,
                'buyer_cr' => $buyerCr !== '' ? $buyerCr : null,
                'buyer_phone' => trim($this->buyerPhone),
                'buyer_email' => trim($this->buyerEmail),
                'buyer_country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
                'buyer_due_date' => DocumentPaymentTerms::isCredit($this->paymentTerms) ? trim($this->dueDate) : '',
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

        $payload['payment_terms'] = $this->paymentTerms;
        $payload['payment_method'] = $this->paymentMethod;
        $payload['amount_received'] = max(0, (float) $this->amountReceived);

        return $payload;
    }

    private function recordInitialPaymentIfNeeded(Invoice $issued): void
    {
        $amount = max(0, (float) $this->amountReceived);
        if ($amount <= 0) {
            return;
        }

        $actor = auth('staff')->user();
        if ($actor === null) {
            return;
        }

        $method = InvoiceArPaymentMethod::tryFrom($this->paymentMethod) ?? InvoiceArPaymentMethod::Cash;

        try {
            app(InvoiceDuesLedgerService::class)->recordPayment(
                $issued,
                now('Asia/Riyadh')->format('Y-m-d'),
                $amount,
                $method,
                null,
                false,
                $actor,
            );
        } catch (\Throwable $e) {
            Log::warning('invoice.issue_initial_payment_failed', ['message' => $e->getMessage()]);
            $this->toastWarning(__('invoices.payment_record_failed'));
        }
    }

    private function signingToastMessage(InvoiceSigningException $exception): string
    {
        $message = mb_strtolower($exception->getMessage());
        if (str_contains($message, 'credential') || str_contains($message, 'encrypted')) {
            return __('invoices.errors.signing_credentials_invalid');
        }

        return SigningFailureSanitizer::userMessage(__('invoices.errors.signing_failed'), $exception);
    }

    protected function afterStandardBuyerCompletionConfirmed(): void
    {
        $this->issue(app(InvoiceIssueService::class));
    }

    public function render(): View
    {
        $isStandard = $this->mode === 'standard';

        $draft = $this->draftInvoiceId !== null ? Invoice::query()->find($this->draftInvoiceId) : null;
        $documentNumberLabel = $draft?->document_number ?? DocumentCreateUiSupport::pendingInvoiceNumberLabel();

        return view('livewire.invoices.issue-invoice', [
            'pageTitle' => __('invoices.create_heading'),
            'isStandard' => $isStandard,
            'standardBuyerMissingKeys' => $isStandard ? $this->standardBuyerCompletionMissingKeys() : [],
            'standardBuyerIncompleteLabels' => $isStandard && $this->selectedCustomerId !== null
                ? StandardBuyerFieldGaps::labelsFor($this)
                : [],
            'customerSearchResults' => $this->customerSearchResults(),
            'addLineProductSearchResults' => $this->addLineProductSearchResults(),
            'canCreateCustomers' => $this->canCreateCustomersFromDocument(),
            'canCreateProducts' => $this->canCreateProductsFromDocument(),
            'productCategories' => QuickProductCategoryOptions::ordered(),
            'previewTotals' => $this->previewTotals(),
            'summaryTotals' => $this->documentSummaryTotals(),
            'paymentDueAmount' => $this->paymentDueAmount(),
            'stockHint' => DocumentCreateUiSupport::stockHintForLines($this->lines),
            'documentNumberLabel' => $documentNumberLabel,
            'customerStrip' => DocumentCreateUiSupport::customerStrip(
                $this->selectedCustomerId,
                $this->buyerName,
                $this->buyerVat,
                $this->buyerPhone,
                $this->buyerEmail,
                $this->addrBuilding,
                $this->addrStreet,
                $this->addrDistrict,
                $this->addrCity,
                $this->addrPostal,
                $this->addrAdditional,
            ),
            'paymentMethodOptions' => [
                InvoiceArPaymentMethod::Cash->value => InvoiceArPaymentMethod::Cash->label(),
                InvoiceArPaymentMethod::Bank->value => InvoiceArPaymentMethod::Bank->label(),
                InvoiceArPaymentMethod::Card->value => InvoiceArPaymentMethod::Card->label(),
            ],
            'paymentTermsIsImmediate' => $this->paymentTermsIsImmediate(),
            'paymentMethodRequired' => $this->paymentMethodRequiredOnForm(),
            'paymentTermsDueDateReadOnly' => $this->paymentTermsIsImmediate()
                || $this->paymentTerms !== DocumentPaymentTerms::CUSTOM,
            'zatcaNotReady' => ! ZatcaSubmissionStatus::canSubmitToZatca($this->business),
        ])->layout('layouts.pos', ['pageTitle' => __('invoices.create_heading')]);
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
            $discountPct = max(0, (float) ($row['discount_percent'] ?? 0));
            $discountEx = round((float) ($row['quantity'] ?? 1) * $unitPrice * $discountPct / 100, 2);

            $lines[] = new InvoiceLineData(
                name: $name !== '' ? $name : __('invoices.line_name'),
                quantity: (float) ($row['quantity'] ?? 1),
                unitPriceExVat: $unitPrice,
                vatRate: (float) ($row['vat_rate'] ?? 0),
                discountExVat: $discountEx,
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

            $this->lines[$index]['vat_rate'] = DocumentCreateUiSupport::normalizeLineVatRate(
                $this->lines[$index]['vat_rate'] ?? '',
                $product->vat_rate,
            );
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
            'line_id' => DocumentCreateUiSupport::newLineId(),
            'name' => '',
            'name_ar' => '',
            'name_bn' => '',
            'quantity' => '1',
            'unit_price' => '',
            'vat_rate' => '15',
            'discount_percent' => '0',
            'product_id' => null,
            'product_unit_id' => null,
            'product_size_id' => null,
            'unit_label' => '',
            'product_image_url' => '',
            'track_stock' => false,
            'stock_quantity' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForMode(bool $relaxedDueDate = false): array
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

        $buyerFields = CustomerIdentityRules::buyerFields();

        $grand = (float) ($this->previewTotals()['total_inc_vat'] ?? 0);
        $received = max(0, (float) $this->amountReceived);

        $paymentRules = $relaxedDueDate
            ? []
            : DocumentPaymentTerms::issueValidationRules(
                $this->paymentTerms,
                $this->effectiveSupplyDate(),
                $grand,
                $received,
                true,
            );

        return array_merge($lineRules, CustomerIdentityRules::forHost($this, $buyerFields, [
            'sa_standard_buyer' => true,
        ]), [
            'selectedCustomerId' => ['required', 'integer', 'exists:customers,id'],
        ], $paymentRules);
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
            'buyerVat.required_if' => __('invoices.errors.buyer_id_required'),
            'buyerAltIdType.required_if' => __('invoices.errors.buyer_id_required'),
            'buyerAltIdNumber.required_if' => __('invoices.errors.buyer_id_required'),
            'selectedCustomerId.required' => __('invoices.errors.customer_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return array_merge(QuickModalValidationAttributes::map(), [
            'buyerName' => __('customers.fields.legal_name_en'),
            'buyerNameAr' => __('customers.fields.legal_name_ar'),
            'buyerNameOptional' => __('invoices.buyer_optional'),
            'buyerVat' => __('customers.fields.vat_number'),
            'buyerAltIdType' => __('customers.fields.alt_id_type'),
            'buyerAltIdNumber' => __('customers.fields.alt_id_number'),
            'buyerCr' => __('business.fields.cr_number'),
            'addrBuilding' => __('business.fields.building'),
            'addrStreet' => __('business.fields.street'),
            'addrDistrict' => __('business.fields.district'),
            'addrCity' => __('business.fields.city'),
            'addrPostal' => __('business.fields.postal'),
            'addrAdditional' => __('business.fields.additional'),
            'dueDate' => __('invoices.due_date'),
            'paymentTerms' => __('invoices.payment_terms_label'),
            'paymentMethod' => __('invoices.payment_received_method'),
            'amountReceived' => __('invoices.payment_received_amount'),
            'buyerCountry' => __('invoices.buyer_country'),
            'lines.*.name' => __('invoices.line_name'),
            'lines.*.quantity' => __('invoices.line_qty'),
            'lines.*.unit_price' => __('invoices.line_price_ex_vat'),
            'lines.*.vat_rate' => __('invoices.line_vat'),
        ]);
    }
}
