<?php

namespace App\Support\Invoicing;

use App\Enums\InvoiceDocumentType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Customers\CustomerIdentityPersistence;
use App\Support\Hub\HubInvoiceZatcaTab;

/**
 * Fills missing standard-invoice buyer snapshot fields from the linked customer record before Z-Heart resubmit.
 */
final class StandardInvoiceBuyerSnapshotRefresh
{
    /**
     * @return array<string, mixed> Updated payload (same array instance merged in place)
     */
    public static function mergeMissingFromCustomer(Invoice $invoice): array
    {
        $payload = is_array($invoice->payload) ? $invoice->payload : [];

        if (HubInvoiceZatcaTab::isSuccess($invoice)) {
            return $payload;
        }

        if (! self::isStandardCategory($payload, $invoice)) {
            return $payload;
        }

        $customer = self::resolveCustomer($invoice, $payload);
        if ($customer === null) {
            return $payload;
        }

        $state = CustomerIdentityPersistence::stateFromCustomer($customer);
        $payload = self::mergeScalar($payload, 'buyer_name', self::firstNonEmpty(
            (string) ($payload['buyer_name'] ?? ''),
            (string) ($state['legal_name_en'] ?? ''),
            (string) ($state['legal_name_ar'] ?? ''),
        ));
        $payload = self::mergeScalar($payload, 'buyer_name_en', self::firstNonEmpty(
            (string) ($payload['buyer_name_en'] ?? ''),
            (string) ($state['legal_name_en'] ?? ''),
        ));
        $payload = self::mergeScalar($payload, 'buyer_name_ar', self::firstNonEmpty(
            (string) ($payload['buyer_name_ar'] ?? ''),
            (string) ($state['legal_name_ar'] ?? ''),
        ));

        if (empty($payload['buyer_vat']) && filled($state['vat_number'] ?? null)) {
            $payload['buyer_vat'] = preg_replace('/\D/', '', (string) $state['vat_number']) ?? '';
            $payload['buyer_vat_registered'] = true;
        }

        if (empty($payload['buyer_cr'])) {
            $altType = strtoupper((string) ($state['alt_id_type'] ?? ''));
            $altNumber = trim((string) ($state['alt_id_number'] ?? ''));
            if ($altType === 'CRN' && $altNumber !== '') {
                $payload['buyer_cr'] = $altNumber;
                $payload['buyer_alt_id_type'] = 'CRN';
                $payload['buyer_alt_id_number'] = $altNumber;
            }
        }

        if (empty($payload['buyer_phone']) && filled($state['phone'] ?? null)) {
            $payload['buyer_phone'] = trim((string) $state['phone']);
        }
        if (empty($payload['buyer_email']) && filled($state['email'] ?? null)) {
            $payload['buyer_email'] = trim((string) $state['email']);
        }

        $country = strtoupper(trim((string) ($payload['buyer_country'] ?? '')));
        if ($country === '') {
            $payload['buyer_country'] = strtoupper((string) ($state['country_code'] ?? 'SA')) ?: 'SA';
        }

        /** @var array<string, mixed> $na */
        $na = (array) ($payload['buyer_national_address'] ?? []);
        $na = self::mergeAddressPart($na, 'building', (string) ($state['addr_building'] ?? ''));
        $na = self::mergeAddressPart($na, 'street', (string) ($state['street_name'] ?? ''));
        $na = self::mergeAddressPart($na, 'district', (string) ($state['addr_district'] ?? ''));
        $na = self::mergeAddressPart($na, 'city', (string) ($state['city'] ?? ''));
        $na = self::mergeAddressPart($na, 'postal_code', (string) ($state['addr_postal'] ?? ''));
        $na = self::mergeAddressPart($na, 'additional', (string) ($state['addr_additional'] ?? ''));

        if (empty($na['country'])) {
            $na['country'] = (string) ($state['country_code'] ?? 'SA');
        }

        $payload['buyer_national_address'] = $na;

        if (! isset($payload['buyer_customer_id'])) {
            $payload['buyer_customer_id'] = $customer->id;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function resolveCustomer(Invoice $invoice, array $payload): ?Customer
    {
        $customerId = $payload['buyer_customer_id'] ?? null;
        if (is_numeric($customerId)) {
            $found = Customer::query()->find((int) $customerId);
            if ($found !== null) {
                return $found;
            }
        }

        $identity = InvoiceCustomerIdentity::resolve($invoice);
        if ($identity['customer_id'] === null) {
            return null;
        }

        return Customer::query()->find($identity['customer_id']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function mergeScalar(array $payload, string $key, string $value): array
    {
        if (trim((string) ($payload[$key] ?? '')) === '' && trim($value) !== '') {
            $payload[$key] = trim($value);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $na
     * @return array<string, mixed>
     */
    private static function mergeAddressPart(array $na, string $key, string $value): array
    {
        if (trim((string) ($na[$key] ?? '')) === '' && trim($value) !== '') {
            $na[$key] = trim($value);
        }

        return $na;
    }

    private static function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function isStandardCategory(array $payload, Invoice $invoice): bool
    {
        $category = (string) ($payload['invoice_category'] ?? '');

        return $category === 'standard' || $invoice->document_type === InvoiceDocumentType::Standard;
    }
}