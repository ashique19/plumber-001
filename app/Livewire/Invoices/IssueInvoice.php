<?php

namespace App\Livewire\Invoices;

use App\Enums\InvoiceDocumentType;
use App\Livewire\Concerns\DispatchesToasts;
use App\Livewire\Concerns\ValidatesFieldsLive;
use App\Models\BusinessSetting;
use App\Models\Branch;
use App\Services\InvoiceIssueService;
use App\Support\StaffBranchContext;
use App\Support\Validation\FieldValidationRules;
use App\Business\BusinessContext;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Livewire\Concerns\ResolvesBusinessContext;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.pos')]
class IssueInvoice extends Component
{
    use ResolvesBusinessContext;
    use DispatchesToasts;
    use ValidatesFieldsLive;


    public string $mode;

    /** @var list<array{name: string, name_ar: string, quantity: string, unit_price: string, vat_rate: string}> */
    public array $lines = [
        ['name' => '', 'name_ar' => '', 'quantity' => '1', 'unit_price' => '', 'vat_rate' => '15'],
    ];

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

    public function mount(string $mode): void
    {
        $this->resolveBusiness();
        abort_unless(in_array($mode, ['standard', 'simplified'], true), 404);

        $business = $this->business;
        $this->mode = $mode;
        BusinessContext::set($business);

        abort_unless(auth('staff')->user()?->can('invoices.issue'), 403);
    }

    public function addLine(): void
    {
        $this->lines[] = ['name' => '', 'name_ar' => '', 'quantity' => '1', 'unit_price' => '', 'vat_rate' => '15'];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) <= 1) {
            return;
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function issue(InvoiceIssueService $issuer): void
    {
        $this->validate($this->rulesForMode());

        if ($this->mode === 'standard') {
            $vatDigits = preg_replace('/\D/', '', $this->buyerVat) ?? '';
            $cr = trim($this->buyerCr);
            if ($vatDigits === '' && $cr === '') {
                $this->addError('buyerVat', __('invoices.errors.buyer_id_required'));

                return;
            }
        }

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
                'buyer_national_address' => [
                    'building' => $this->addrBuilding,
                    'street' => $this->addrStreet,
                    'district' => $this->addrDistrict,
                    'city' => $this->addrCity,
                    'postal_code' => $this->addrPostal,
                    'additional' => $this->addrAdditional,
                ],
            ]);
        } else {
            if (trim((string) $this->buyerNameOptional) !== '') {
                $payload['buyer_name'] = trim($this->buyerNameOptional);
            }
            if (trim($this->buyerPhone) !== '') {
                $payload['buyer_phone'] = trim($this->buyerPhone);
            }
            if (trim($this->buyerEmail) !== '') {
                $payload['buyer_email'] = trim($this->buyerEmail);
            }
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

        $issued = $issuer->issue($draft->fresh());
        $this->toastSuccess(__('invoices.issue_success', ['number' => $issued->document_number]));
        $this->redirect(route('admin.invoices'), navigate: true);
    }

    public function render(): View
    {
        $isStandard = $this->mode === 'standard';

        return view('livewire.invoices.issue-invoice', [
            'pageTitle' => $isStandard
                ? __('invoices.issue_standard_title')
                : __('invoices.issue_simplified_title'),
            'isStandard' => $isStandard,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForMode(): array
    {
        $lineRules = [
            'lines' => ['required', 'array', 'min:1'],
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
        ];

        if ($this->mode === 'simplified') {
            return array_merge($lineRules, [
                'buyerNameOptional' => ['nullable', 'string', 'max:200'],
            ]);
        }

        return array_merge($lineRules, [
            'buyerName' => ['required', 'string', 'max:200'],
            'buyerVat' => FieldValidationRules::vatOptional(),
            'buyerCr' => FieldValidationRules::crNumberOptional(),
            'addrBuilding' => ['required', 'string', 'max:32'],
            'addrStreet' => ['required', 'string', 'max:120'],
            'addrDistrict' => ['nullable', 'string', 'max:120'],
            'addrCity' => ['required', 'string', 'max:80'],
            'addrPostal' => FieldValidationRules::postalCodeRequired(),
            'addrAdditional' => ['nullable', 'string', 'max:32'],
        ]);
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
            'lines.*.name' => __('invoices.line_name'),
            'lines.*.quantity' => __('invoices.line_qty'),
            'lines.*.unit_price' => __('invoices.line_price_ex_vat'),
            'lines.*.vat_rate' => __('invoices.line_vat'),
        ];
    }
}
