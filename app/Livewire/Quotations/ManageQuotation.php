<?php

namespace App\Livewire\Quotations;

use App\Data\InvoiceLineData;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ManagesInvoicingCatalogPicker;
use App\Livewire\Concerns\ManagesInvoicingCustomerPicker;
use App\Livewire\Concerns\ResolvesBusinessContext;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Quotation;
use App\Support\Invoicing\InvoiceDocumentTotals;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use App\Business\BusinessContext;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class ManageQuotation extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use ManagesInvoicingCatalogPicker;
    use ManagesInvoicingCustomerPicker;
    use ValidatesFieldsLive;

  public ?int $quotationId = null;

  public string $invoiceMode = 'simplified';

  public string $validityUntil = '';

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

  public string $supplyDate = '';

  public string $documentNotes = '';

  public function mount(?Quotation $quotation = null): void
  {
      $this->resolveBusiness();
      BusinessContext::set($this->business);
      app(PermissionRegistrar::class)->setPermissionsTeamId($this->business->id);
      abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);

      $defaultMode = $this->business->defaultInvoiceDocumentType()->value;
      $this->invoiceMode = in_array($defaultMode, ['standard', 'simplified'], true) ? $defaultMode : 'simplified';
      $this->validityUntil = now('Asia/Riyadh')->addDays(30)->format('Y-m-d');
      $this->supplyDate = now('Asia/Riyadh')->format('Y-m-d');

      if ($quotation !== null) {
          $this->quotationId = $quotation->id;
          $this->hydrateFromQuotation($quotation);
      }
  }

  private function hydrateFromQuotation(Quotation $quotation): void
  {
      $this->invoiceMode = in_array($quotation->invoice_mode, ['standard', 'simplified'], true)
          ? $quotation->invoice_mode
          : $this->invoiceMode;
      $this->validityUntil = $quotation->validity_until?->format('Y-m-d') ?? $this->validityUntil;

      $payload = $quotation->payload ?? [];
      $this->invoiceDiscountExVat = (string) ($payload['invoice_discount_ex_vat'] ?? '0');
      $this->retentionPercent = isset($payload['retention_percent']) ? (string) $payload['retention_percent'] : '';
      $this->retentionFixed = isset($payload['retention_fixed']) ? (string) $payload['retention_fixed'] : '';
      $this->buyerPhone = (string) ($payload['buyer_phone'] ?? '');
      $this->buyerEmail = (string) ($payload['buyer_email'] ?? '');
      $this->buyerName = (string) ($payload['buyer_name'] ?? '');
      $this->buyerVat = (string) ($payload['buyer_vat'] ?? '');
      $this->buyerCr = (string) ($payload['buyer_cr'] ?? '');
      $this->buyerNameOptional = (string) ($payload['buyer_name'] ?? '');
      $this->buyerCountry = (string) ($payload['buyer_country'] ?? 'SA');

      $na = (array) ($payload['buyer_national_address'] ?? []);
      $this->addrBuilding = (string) ($na['building'] ?? '');
      $this->addrStreet = (string) ($na['street'] ?? '');
      $this->addrDistrict = (string) ($na['district'] ?? '');
      $this->addrCity = (string) ($na['city'] ?? 'Riyadh');
      $this->addrPostal = (string) ($na['postal_code'] ?? '');
      $this->addrAdditional = (string) ($na['additional'] ?? '');

      $rawLines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
      $this->lines = [];
      foreach ($rawLines as $row) {
          if (! is_array($row)) {
              continue;
          }
          $this->lines[] = [
              'name' => (string) ($row['name'] ?? ''),
              'name_ar' => (string) ($row['name_ar'] ?? ''),
              'quantity' => (string) ($row['quantity'] ?? '1'),
              'unit_price' => (string) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? ''),
              'vat_rate' => (string) ($row['vat_rate'] ?? '15'),
              'product_id' => $row['product_id'] ?? null,
              'unit_label' => (string) ($row['unit_label'] ?? ''),
          ];
      }

      $this->supplyDate = (string) ($payload['supply_date'] ?? $this->supplyDate);
      $this->documentNotes = (string) ($payload['document_notes'] ?? '');
  }

  public function removeLine(int $index): void
  {
      unset($this->lines[$index]);
      $this->lines = array_values($this->lines);
  }

  protected function documentStandardBuyerRequired(): bool
  {
      return $this->invoiceMode === 'standard';
  }

  public function save(): void
  {
      $this->pruneEmptyDocumentLines();
      $this->hydrateCatalogLineNames();
      $this->validate($this->rules());

      $branch = StaffBranchContext::resolve();
      if ($branch === null) {
          $this->toastError(__('pos.empty_branches'));

          return;
      }
      StaffBranchContext::remember($branch);

      $payload = $this->buildPayload();

      $quotation = $this->quotationId !== null
          ? Quotation::query()->findOrFail($this->quotationId)
          : new Quotation([
              'quotation_number' => $this->nextQuotationNumber(),
              'created_by_staff_user_id' => auth('staff')->id(),
          ]);

      $quotation->forceFill([
          'branch_id' => $branch->id,
          'invoice_mode' => $this->invoiceMode,
          'validity_until' => $this->validityUntil,
          'payload' => $payload,
      ])->save();

      $this->toastSuccess(__('quotations.save_success', ['number' => $quotation->quotation_number]));
      $this->redirect(route('admin.quotations'), navigate: true);
  }

  /**
   * @return array<string, mixed>
   */
  private function buildPayload(): array
  {
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
          if (filled($row['product_id'] ?? null)) {
              $item['product_id'] = (int) $row['product_id'];
          }

          return $item;
      }, $this->lines);

      $payload = ['lines' => $lineItems];

      if ($this->invoiceMode === 'standard') {
          $vat = preg_replace('/\D/', '', $this->buyerVat) ?? '';
          $payload = array_merge($payload, [
              'buyer_name' => trim($this->buyerName),
              'buyer_vat' => $vat,
              'buyer_cr' => trim($this->buyerCr),
              'buyer_phone' => trim($this->buyerPhone),
              'buyer_email' => trim($this->buyerEmail),
              'buyer_country' => strtoupper(trim($this->buyerCountry)) ?: 'SA',
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

      return $payload;
  }

  private function nextQuotationNumber(): string
  {
      return 'QUO-'.now('Asia/Riyadh')->format('Ymd-His');
  }

  public function render(): View
  {
      $isStandard = $this->invoiceMode === 'standard';
      $isEdit = $this->quotationId !== null;

      return view('livewire.quotations.manage-quotation', [
          'pageTitle' => $isEdit ? __('quotations.edit_title') : __('quotations.create_title'),
          'isStandard' => $isStandard,
          'customerSearchResults' => $this->customerSearchResults(),
          'addLineProductSearchResults' => $this->addLineProductSearchResults(),
          'canCreateCustomers' => $this->canCreateCustomersFromDocument(),
          'canCreateProducts' => $this->canCreateProductsFromDocument(),
          'productCategories' => ProductCategory::query()->orderBy('name_en')->get(),
          'previewTotals' => $this->previewTotals(),
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
  private function rules(): array
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
          'validityUntil' => ['required', 'date'],
          'invoiceMode' => ['required', 'in:standard,simplified'],
          'supplyDate' => ['nullable', 'date'],
          'documentNotes' => ['nullable', 'string', 'max:2000'],
      ];

      if ($this->invoiceMode === 'simplified') {
          return $lineRules;
      }

      return array_merge($lineRules, [
          'selectedCustomerId' => ['required', 'integer', 'exists:customers,id'],
          'buyerName' => ['required', 'string', 'max:200'],
          'buyerVat' => array_merge(FieldValidationRules::vatOptional(), ['required_without:buyerCr']),
          'buyerCr' => array_merge(FieldValidationRules::crNumberOptional(), ['required_without:buyerVat']),
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
      return $this->rules();
  }

  /**
   * @return array<string, string>
   */
  protected function validationAttributes(): array
  {
      return [
          'validityUntil' => __('quotations.validity_until'),
          'buyerName' => __('invoices.buyer_name'),
          'buyerNameOptional' => __('invoices.buyer_optional'),
          'lines.*.name' => __('invoices.line_name'),
          'lines.*.quantity' => __('invoices.line_qty'),
          'lines.*.unit_price' => __('invoices.line_price_ex_vat'),
          'lines.*.vat_rate' => __('invoices.line_vat'),
      ];
  }
}