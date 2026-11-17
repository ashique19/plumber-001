<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceArPaymentMethod;
use App\Enums\InvoiceCollectionStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PosOrderStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosOrder;
use App\Models\StaffUser;
use App\Support\Hub\HubChronologicalSort;
use App\Support\Invoicing\InvoiceCustomerIdentity;
use App\Support\Invoicing\InvoicePayloadLines;
use Illuminate\Support\Collection;

class InvoiceDuesLedgerService
{
    /**
     * @return array{
     *     net_due: float,
     *     retention_due: float,
     *     total_inc_vat: float,
     *     principal_paid: float,
     *     retention_paid: float,
     *     principal_outstanding: float,
     *     retention_outstanding: float,
     *     implied_pos_paid: float,
     *     status: InvoiceCollectionStatus
     * }
     */
    public function balanceFor(Invoice $invoice, ?Collection $payments = null): array
    {
        $totals = InvoicePayloadLines::totalsFromInvoice($invoice);
        $netDue = (float) $totals['net_due_inc_vat'];
        $retentionDue = (float) $totals['retention_amount'];
        $totalInc = (float) $totals['total_inc_vat'];

        $payments ??= $invoice->relationLoaded('payments')
          ? $invoice->payments
          : $invoice->payments()->get();

        $recordedPrincipal = (float) $payments->where('for_retention', false)->sum('amount');
        $recordedRetention = (float) $payments->where('for_retention', true)->sum('amount');

        $impliedPosPaid = $this->impliedPosSettlement($invoice, $netDue);

        $principalPaid = round($recordedPrincipal + $impliedPosPaid, 2);
        $retentionPaid = round($recordedRetention, 2);

        $principalOutstanding = round(max(0, $netDue - $principalPaid), 2);
        $retentionOutstanding = round(max(0, $retentionDue - $retentionPaid), 2);

        $status = InvoiceCollectionStatus::Unpaid;
        if ($principalPaid >= $netDue && $netDue > 0) {
            $status = InvoiceCollectionStatus::Paid;
        } elseif ($principalPaid > 0) {
            $status = InvoiceCollectionStatus::Partial;
        } elseif ($netDue <= 0) {
            $status = InvoiceCollectionStatus::Paid;
        }

        return [
            'net_due' => $netDue,
            'retention_due' => $retentionDue,
            'total_inc_vat' => $totalInc,
            'principal_paid' => $principalPaid,
            'retention_paid' => $retentionPaid,
            'principal_outstanding' => $principalOutstanding,
            'retention_outstanding' => $retentionOutstanding,
            'implied_pos_paid' => $impliedPosPaid,
            'status' => $status,
        ];
    }

    private function impliedPosSettlement(Invoice $invoice, float $netDue): float
    {
        if ($netDue <= 0 || $invoice->source_type !== PosOrder::class || $invoice->source_id === null) {
            return 0.0;
        }

        $order = PosOrder::query()->find($invoice->source_id);
        if ($order === null || $order->status !== PosOrderStatus::Paid) {
            return 0.0;
        }

        return round($netDue, 2);
    }

    public function recordPayment(
        Invoice $invoice,
        string $paidOn,
        float $amount,
        InvoiceArPaymentMethod $method,
        ?string $reference,
        bool $forRetention,
        StaffUser $actor,
    ): InvoicePayment {
        if (! $invoice->isIssued()) {
            throw new \InvalidArgumentException('Only issued invoices accept payments.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive.');
        }

        $balance = $this->balanceFor($invoice->fresh(['payments']));
        $max = $forRetention ? $balance['retention_outstanding'] : $balance['principal_outstanding'];

        if ($max <= 0) {
            throw new \InvalidArgumentException('Nothing outstanding for this allocation.');
        }

        if ($amount > $max + 0.0001) {
            throw new \InvalidArgumentException(__('payments.errors.amount_exceeds_outstanding'));
        }

        $identity = InvoiceCustomerIdentity::resolve($invoice);

        return InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $identity['customer_id'],
            'paid_on' => $paidOn,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference' => filled($reference) ? trim($reference) : null,
            'for_retention' => $forRetention,
            'recorded_by_id' => $actor->id,
        ]);
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function collectibleInvoices(): Collection
    {
        return Invoice::query()
            ->where('status', InvoiceStatus::Issued)
            ->whereIn('document_type', [
                InvoiceDocumentType::Standard,
                InvoiceDocumentType::Simplified,
            ])
            ->with(['payments'])
            ->tap(fn ($q) => HubChronologicalSort::applyLatestFirst($q, 'issued_at'))
            ->get();
    }

    /**
     * @return list<array{
     *     key: string,
     *     customer_id: int|null,
     *     name: string,
     *     phone: string|null,
     *     email: string|null,
     *     principal_outstanding: float,
     *     retention_outstanding: float,
     *     invoice_count: int
     * }>
     */
    public function customerOutstandingSummary(): array
    {
        $buckets = [];

        foreach ($this->collectibleInvoices() as $invoice) {
            $balance = $this->balanceFor($invoice);
            $principal = $balance['principal_outstanding'];
            $retention = $balance['retention_outstanding'];

            if ($principal <= 0 && $retention <= 0) {
                continue;
            }

            $identity = InvoiceCustomerIdentity::resolve($invoice);
            $key = $identity['key'];

            if (! isset($buckets[$key])) {
                $buckets[$key] = [
                    'key' => $key,
                    'customer_id' => $identity['customer_id'],
                    'name' => InvoiceCustomerIdentity::displayName($identity),
                    'phone' => $identity['phone'],
                    'email' => $identity['email'],
                    'principal_outstanding' => 0.0,
                    'retention_outstanding' => 0.0,
                    'invoice_count' => 0,
                ];
            }

            $buckets[$key]['principal_outstanding'] = round($buckets[$key]['principal_outstanding'] + $principal, 2);
            $buckets[$key]['retention_outstanding'] = round($buckets[$key]['retention_outstanding'] + $retention, 2);
            $buckets[$key]['invoice_count']++;
        }

        usort($buckets, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return array_values($buckets);
    }

    /**
     * @return array{
     *     customer: array<string, mixed>,
     *     invoices: list<array<string, mixed>>,
     *     payments: list<array<string, mixed>>
     * }
     */
    public function customerStatement(string $customerKey): array
    {
        $matchingIdentity = null;
        $invoiceRows = [];
        $paymentRows = [];

        foreach ($this->collectibleInvoices() as $invoice) {
            $identity = InvoiceCustomerIdentity::resolve($invoice);
            if ($identity['key'] !== $customerKey) {
                continue;
            }

            $matchingIdentity ??= $identity;
            $balance = $this->balanceFor($invoice);

            $invoiceRows[] = [
                'invoice' => $invoice,
                'document_number' => $invoice->document_number,
                'issued_at' => $invoice->issued_at,
                'due_date' => $invoice->due_date,
                'status' => $balance['status'],
                'net_due' => $balance['net_due'],
                'retention_due' => $balance['retention_due'],
                'principal_outstanding' => $balance['principal_outstanding'],
                'retention_outstanding' => $balance['retention_outstanding'],
            ];

            foreach ($invoice->payments as $payment) {
                $paymentRows[] = [
                    'payment' => $payment,
                    'document_number' => $invoice->document_number,
                    'paid_on' => $payment->paid_on,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'reference' => $payment->reference,
                    'for_retention' => (bool) $payment->for_retention,
                ];
            }
        }

        if ($matchingIdentity === null) {
            throw new \InvalidArgumentException('Customer not found.');
        }

        $invoiceRows = HubChronologicalSort::sortRowsByIssuedCreatedId($invoiceRows);

        usort($paymentRows, static fn (array $a, array $b): int => strcmp($b['paid_on']?->toDateString() ?? '', $a['paid_on']?->toDateString() ?? ''));

        return [
            'customer' => [
                'key' => $customerKey,
                'customer_id' => $matchingIdentity['customer_id'],
                'name' => InvoiceCustomerIdentity::displayName($matchingIdentity),
                'phone' => $matchingIdentity['phone'],
                'email' => $matchingIdentity['email'],
            ],
            'invoices' => $invoiceRows,
            'payments' => $paymentRows,
        ];
    }

    /**
     * @param  list<int>  $customerIds
     * @return array<int, float>
     */
    public function totalOutstandingForCustomerIds(array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $lookup = array_fill_keys($customerIds, 0.0);
        $idSet = array_flip($customerIds);

        foreach ($this->collectibleInvoices() as $invoice) {
            $identity = InvoiceCustomerIdentity::resolve($invoice);
            $customerId = $identity['customer_id'];
            if ($customerId === null || ! isset($idSet[$customerId])) {
                continue;
            }

            $balance = $this->balanceFor($invoice);
            $due = $balance['principal_outstanding'] + $balance['retention_outstanding'];
            if ($due > 0) {
                $lookup[$customerId] = round($lookup[$customerId] + $due, 2);
            }
        }

        return $lookup;
    }

    public function totalOutstandingPrincipal(): float
    {
        $total = 0.0;

        foreach ($this->collectibleInvoices() as $invoice) {
            $total += $this->balanceFor($invoice)['principal_outstanding'];
        }

        return round($total, 2);
    }
}
