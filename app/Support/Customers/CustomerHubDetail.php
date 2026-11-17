<?php

namespace App\Support\Customers;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\StaffUser;
use App\Services\Invoicing\InvoiceDuesLedgerService;
use App\Support\Hub\HubInvoiceTableRow;
use App\Support\Hub\HubQuotationPrintLinks;
use App\Support\Invoicing\InvoiceCustomerIdentity;
use App\Data\InvoiceLineData;
use App\Support\Invoicing\InvoiceDocumentTotals;
use Illuminate\Support\Str;

final class CustomerHubDetail
{
    private const RECENT_INVOICES = 8;

    private const RECENT_PAYMENTS = 10;

    private const RECENT_QUOTATIONS = 8;

    /**
     * @return array{
     *     customer_key: string,
     *     contact: array<string, string|null>,
     *     address_lines: list<string>,
     *     totals: array{invoiced: float, paid: float, due: float, retention: float},
     *     invoices: list<array<string, mixed>>,
     *     payments: list<array<string, mixed>>,
     *     quotations: list<array<string, mixed>>,
     *     can_issue: bool,
     *     statement_url: string
     * }
     */
    public static function build(Customer $customer, InvoiceDuesLedgerService $ledger, ?StaffUser $actor): array
    {
        $customerKey = 'customer:'.$customer->id;

        $invoiced = 0.0;
        $paid = 0.0;
        $due = 0.0;
        $retention = 0.0;
        $invoiceRows = [];
        $paymentRows = [];

        try {
            $statement = $ledger->customerStatement($customerKey);
            foreach ($statement['invoices'] as $row) {
                $balance = $ledger->balanceFor($row['invoice']);
                $invoiced += $balance['total_inc_vat'];
                $paid += $balance['principal_paid'] + $balance['retention_paid'];
                $due += $balance['principal_outstanding'];
                $retention += $balance['retention_outstanding'];
            }

            $invoiceRows = array_slice(
                array_map(
                    static function (array $row) use ($ledger): array {
                        $hub = HubInvoiceTableRow::fromInvoice($row['invoice'], $ledger);

                        return array_merge($row, [
                            'actions' => $hub['actions'],
                            'more' => $hub['more'],
                            'view_url' => $hub['view_url'],
                            'print_urls' => $hub['print_urls'],
                        ]);
                    },
                    $statement['invoices'],
                ),
                0,
                self::RECENT_INVOICES,
            );

            $paymentRows = array_slice($statement['payments'], 0, self::RECENT_PAYMENTS);
        } catch (\InvalidArgumentException) {
            // No issued invoices yet for this customer key.
        }

        $quotationRows = self::recentQuotations($customer, $actor);

        $state = CustomerIdentityPersistence::stateFromCustomer($customer);
        $naParts = array_filter([
            $state['addr_building'] ?? '',
            $state['street_name'] ?? '',
            $state['addr_district'] ?? '',
            $state['city'] ?? '',
            $state['addr_postal'] ?? '',
            $state['country_code'] ?? 'SA',
        ], static fn (string $v): bool => trim($v) !== '');

        return [
            'customer_key' => $customerKey,
            'contact' => [
                'name' => CustomerDisplayName::for($customer),
                'phone' => filled($customer->phone) ? trim((string) $customer->phone) : null,
                'email' => filled($customer->email) ? trim((string) $customer->email) : null,
                'vat' => filled($customer->vat_number) ? trim((string) $customer->vat_number) : null,
                'cr' => CustomerProfileFields::crNumber($customer),
            ],
            'address_lines' => array_values($naParts),
            'totals' => [
                'invoiced' => round($invoiced, 2),
                'paid' => round($paid, 2),
                'due' => round($due, 2),
                'retention' => round($retention, 2),
            ],
            'invoices' => $invoiceRows,
            'payments' => $paymentRows,
            'quotations' => $quotationRows,
            'can_issue' => $actor?->can('invoices.issue') ?? false,
            'statement_url' => route('admin.payments.dues', ['customerKey' => $customerKey]),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function recentQuotations(Customer $customer, ?StaffUser $actor): array
    {
        $phone = InvoiceCustomerIdentity::normalizePhone((string) ($customer->phone ?? ''));
        $email = InvoiceCustomerIdentity::normalizeEmail((string) ($customer->email ?? ''));
        $name = Str::lower(trim($customer->name));

        $rows = [];
        $canManage = $actor?->can('invoices.issue') ?? false;

        Quotation::query()
            ->orderByDesc('created_at')
            ->limit(60)
            ->get()
            ->each(function (Quotation $quotation) use (&$rows, $phone, $email, $name, $canManage): void {
                if (count($rows) >= self::RECENT_QUOTATIONS) {
                    return;
                }

                $payload = $quotation->payload ?? [];
                $qPhone = InvoiceCustomerIdentity::normalizePhone((string) ($payload['buyer_phone'] ?? ''));
                $qEmail = InvoiceCustomerIdentity::normalizeEmail((string) ($payload['buyer_email'] ?? ''));
                $qName = Str::lower(trim((string) ($payload['buyer_name'] ?? '')));

                $match = ($phone !== null && $qPhone === $phone)
                    || ($email !== null && $qEmail === $email)
                    || ($name !== '' && $qName === $name);

                if (! $match) {
                    return;
                }

                $urls = HubQuotationPrintLinks::urls($quotation);
                $lineModels = [];
                foreach ((array) ($quotation->payload['lines'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $lineModels[] = new InvoiceLineData(
                        name: (string) ($row['name'] ?? ''),
                        quantity: (float) ($row['quantity'] ?? 1),
                        unitPriceExVat: (float) ($row['unit_price_ex_vat'] ?? $row['unit_price'] ?? 0),
                        vatRate: (float) ($row['vat_rate'] ?? 15),
                        discountExVat: (float) ($row['discount_ex_vat'] ?? 0),
                    );
                }
                $totals = InvoiceDocumentTotals::compute(
                    $lineModels,
                    (float) ($quotation->payload['invoice_discount_ex_vat'] ?? 0),
                    isset($quotation->payload['retention_percent']) ? (float) $quotation->payload['retention_percent'] : null,
                    isset($quotation->payload['retention_fixed']) ? (float) $quotation->payload['retention_fixed'] : null,
                );

                $rows[] = [
                    'quotation' => $quotation,
                    'quotation_number' => $quotation->quotation_number,
                    'created_at' => $quotation->created_at,
                    'total_inc_vat' => $totals['total_inc_vat'],
                    'print_urls' => $urls,
                    'can_manage' => $canManage,
                ];
            });

        return $rows;
    }
}